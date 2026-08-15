<?php

namespace App\Domain\EloSnapshot;

/**
 * Décide si un relevé mérite une ligne (« change data capture »).
 *
 * Le cron passe toutes les 30 minutes, soit 48 relevés par compte et par file
 * et par jour, dont la très grande majorité est identique au précédent. On
 * n'écrit que ce qui apporte une information.
 */
final class EloChangeDetector
{
    public static function shouldRecord(?EloSnapshot $last, EloSnapshot $candidate): bool
    {
        // Premier relevé connu pour ce compte et cette file.
        if ($last === null) {
            return true;
        }

        // Garde de monotonie : run concurrent, horloge revenue en arrière, ou
        // heure d'hiver où 02:30 existe deux fois. Sans elle, on viole l'unique
        // (compte, file, instant) et le flush ferme l'EntityManager.
        if ($candidate->capturedAt <= $last->capturedAt) {
            return false;
        }

        // Le rang a bougé : c'est l'information qu'on cherche.
        if (!$candidate->hasSameRankAs($last)) {
            return true;
        }

        // Battement quotidien : au plus une ligne « sans changement » par jour.
        // Garantit à chaque fenêtre de course un point de départ proche de sa
        // borne, sans dépendre de la reprise du dernier point antérieur.
        return $last->capturedAt < $candidate->capturedAt->setTime(0, 0);
    }
}
