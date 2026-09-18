<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;

/**
 * m260918_000000_class_notification_emails_2026 migration.
 *
 * Replaces the `notificationEmails` table field on each class entry with the
 * parent addresses from the 2026-2027 student directory
 * (storage/notifications/2026_emails-by-grade.json). The addresses are embedded
 * here rather than read from storage so the migration doesn't depend on that
 * file being deployed.
 *
 * Existing addresses are replaced, not merged, so last year's families stop
 * receiving updates for classes they've moved out of.
 */
class m260918_000000_class_notification_emails_2026 extends Migration
{
    /**
     * Class entry slugs mapped to their notification addresses. Note the
     * Kindergarten entry's slug is spelled `kindergaten` on the live site.
     */
    private const EMAILS_BY_CLASS_SLUG = [
        // Junior Kindergarten 3 (22)
        'jk3' => [
            'austinhogston@gmail.com',
            'ccbrue91@gmail.com',
            'champaynewilson@gmail.com',
            'dan.lapp29@gmail.com',
            'david.joseph.dimaggio@gmail.com',
            'devan.classi@gmail.com',
            'dustin.kilpatrick@unchealth.unc.edu',
            'edmundson.sarahe@gmail.com',
            'erinschotte@gmail.com',
            'hmquinn825@gmail.com',
            'jayme.cannon@unchealth.unc.edu',
            'jcoverton2@gmail.com',
            'jdm5748@gmail.com',
            'kahsaraj@gmail.com',
            'mattybtatt@icloud.com',
            'mbbodenh@gmail.com',
            'rhogston16@gmail.com',
            'richardpinkertonjr@gmail.com',
            'rzhaynes2010@gmail.com',
            'sedmundson@gmail.com',
            'tiffani.dimaggio19@gmail.com',
            'vinrussello@gmail.com',
        ],
        // Junior Kindergarten 4 (26)
        'jk4' => [
            'aaronmedina.media@gmail.com',
            'agusta.patton@gmail.com',
            'alina.soderholm@gmail.com',
            'audreybentley0708@gmail.com',
            'bjohnson@alumni.unc.edu',
            'cbentley8088@gmail.com',
            'cjashort@gmail.com',
            'clearwcj@gmail.com',
            'deannalshort@gmail.com',
            'edmundson.sarahe@gmail.com',
            'gesmedina31@gmail.com',
            'jamesruggles1212@gmail.com',
            'jbdsgns@gmail.com',
            'jla304@gmail.com',
            'jmargo4312@gmail.com',
            'josuemgiron@gmail.com',
            'keefe.honda@gmail.com',
            'kmheeth5@gmail.com',
            'kwmitchell2020@gmail.com',
            'nvannispen@gmail.com',
            'owensjennalee@gmail.com',
            'patiencegiron@gmail.com',
            'ryanm2010@gmail.com',
            'sedmundson@gmail.com',
            'shumate14@me.com',
            'ugarach@gmail.com',
        ],
        // Kindergarten (26)
        'kindergaten' => [
            'agusta.patton@gmail.com',
            'aldcromwell@gmail.com',
            'amberlamburn@gmail.com',
            'andrew.godek@gmail.com',
            'ardenclairejones@gmail.com',
            'bakermaher@yahoo.com',
            'blawler19@gmail.com',
            'bmcclanahan06@gmail.com',
            'bmcmahan91@gmail.com',
            'darianbouley@yahoo.com',
            'domgebben@gmail.com',
            'drewamburn@gmail.com',
            'got1750@gmail.com',
            'jamiewright2425@yahoo.com',
            'josuemgiron@gmail.com',
            'mejarzo@yahoo.com',
            'nathan.cromwell15@gmail.com',
            'nleenette@aol.com',
            'nvannispen@gmail.com',
            'patiencegiron@gmail.com',
            'ramseurpe@yahoo.com',
            'rpcope409@gmail.com',
            'saraxjayne@gmail.com',
            'shannadanae21@gmail.com',
            'wescamp17@gmail.com',
            'wmfaggar4@gmail.com',
        ],
        // 1st Grade (20)
        'first-grade' => [
            'bcadair@charter.net',
            'breannarose18@gmail.com',
            'bree.jeanne@gmail.com',
            'brendatilem@gmail.com',
            'britrbowman@yahoo.com',
            'carleysmith3294@gmail.com',
            'fixitconstructionllc@gmail.com',
            'gsaez01@gmail.com',
            'johntrigney@gmail.com',
            'jschmeelk@gmail.com',
            'marine_boxing@yahoo.com',
            'mpmconnelly@gmail.com',
            'msuvet@gmail.com',
            'paigehcork@gmail.com',
            'pgbristol@gmail.com',
            'rmd75nc@yahoo.com',
            'smithracing619@gmail.com',
            'smmovaghar@gmail.com',
            'steventilem@gmail.com',
            'vetman1983@aol.com',
        ],
        // 2nd Grade (33)
        'second-grade' => [
            'a.catochapman@gmail.com',
            'bcepley@gmail.com',
            'bethanyepley@gmail.com',
            'bmcmahan91@gmail.com',
            'ccbrue91@gmail.com',
            'cholshouser@southmountain.org',
            'clearwcj@gmail.com',
            'dan.lapp29@gmail.com',
            'dldiaz8@gmail.com',
            'dustin.kilpatrick@unchealth.unc.edu',
            'emfisher827@gmail.com',
            'emilyemanuelpatterson@gmail.com',
            'jayme.cannon@unchealth.unc.edu',
            'jbdsgns@gmail.com',
            'jla304@gmail.com',
            'jmargo4312@gmail.com',
            'jonbrisson@gmail.com',
            'jwhite16@outlook.com',
            'kairlombardi@gmail.com',
            'kcampbell199@icloud.com',
            'keefe.honda@gmail.com',
            'kmheeth5@gmail.com',
            'kwmitchell2020@gmail.com',
            'laurenbpeach90@gmail.com',
            'lyndsaybrisson@gmail.com',
            'racheljost@yahoo.com',
            'ramseurpe@yahoo.com',
            'robertglutherie@gmail.com',
            'rpcope409@gmail.com',
            'ryanm2010@gmail.com',
            'scatochapman@morgantondayschool.com',
            'willchestercpt@gmail.com',
            'wmfaggar4@gmail.com',
        ],
        // 3rd Grade (14)
        'third-grade' => [
            'almgwm@gmail.com',
            'amberlamburn@gmail.com',
            'amberlmank@gmail.com',
            'bakermaher@yahoo.com',
            'brendatilem@gmail.com',
            'colleen.bennett@cbbdesignfirm.com',
            'daniel@kulinski.net',
            'david@benchmadeventures.com',
            'de.franklin4113@gmail.com',
            'drewamburn@gmail.com',
            'grahamcornellphoto@gmail.com',
            'saraxjayne@gmail.com',
            'steventilem@gmail.com',
            'vickie@kulinski.net',
        ],
        // 4th Grade (22)
        'fourth-grade' => [
            'a.catochapman@gmail.com',
            'aalexander1976@gmail.com',
            'bncato06@gmail.com',
            'bree.jeanne@gmail.com',
            'cbarbosa@morgantondayschool.com',
            'chrisleeking@yahoo.com',
            'efeduke@morgantondayschool.com',
            'emilyross5678@gmail.com',
            'foothillsbass@gmail.com',
            'garrettfeduke@morgantonhonda.biz',
            'heavan.dixon@gmail.com',
            'jason.peeler1974@gmail.com',
            'jla304@gmail.com',
            'jmargo4312@gmail.com',
            'jortrev98@gmail.com',
            'kevin@mimiskidzdaycare.com',
            'natashazalomski@gmail.com',
            'pgbristol@gmail.com',
            'rycato@gmail.com',
            'scatochapman@morgantondayschool.com',
            'themeserveyfamily@gmail.com',
            'triciahp75@gmail.com',
        ],
        // 5th Grade (16)
        'fifth-grade' => [
            'almgwm@gmail.com',
            'amberlmank@gmail.com',
            'audreybentley0708@gmail.com',
            'bluman0880@icloud.com',
            'cbentley8088@gmail.com',
            'cjashort@gmail.com',
            'deannalshort@gmail.com',
            'fixitconstructionllc@gmail.com',
            'gibbons.fam02@gmail.com',
            'jaredtamos@gmail.com',
            'jennalouise@gmail.com',
            'mpmconnelly@gmail.com',
            'nicole_nelson@myyahoo.com',
            'nse555@yahoo.com',
            'peterpaulhernandez@gmail.com',
            'snowsherrill@gmail.com',
        ],
        // 6th Grade (21)
        'sixth-grade' => [
            'brandiemariemartin@gmail.com',
            'brettwithrow@yahoo.com',
            'carriegriffin2022@gmail.com',
            'chrisleeking@yahoo.com',
            'colleen.bennett@cbbdesignfirm.com',
            'danaswithrow@yahoo.com',
            'david@benchmadeventures.com',
            'dunatis@gmail.com',
            'dylyngriffin90@gmail.com',
            'jacobrhovis@gmail.com',
            'jacquelynhovis@gmail.com',
            'jonbrisson@gmail.com',
            'karawalton44@gmail.com',
            'kemerling.58@gmail.com',
            'kenriquez@morgantondayschool.com',
            'larakemerling@gmail.com',
            'lyndsaybrisson@gmail.com',
            'mit.enriquez@gmail.com',
            'natashazalomski@gmail.com',
            'smith.carolyn28@yahoo.com',
            'twalton2386@gmail.com',
        ],
        // 7th Grade (4)
        'seventh-grade' => [
            'cbarbosa@morgantondayschool.com',
            'jortrev98@gmail.com',
            'trena.patton@gmail.com',
            'vagnhansen79@gmail.com',
        ],
        // 8th Grade (8)
        'eighth-grade' => [
            'cjashort@gmail.com',
            'deannalshort@gmail.com',
            'efeduke@morgantondayschool.com',
            'farrhiker42@gmail.com',
            'garrettfeduke@morgantonhonda.biz',
            'kenriquez@morgantondayschool.com',
            'mit.enriquez@gmail.com',
            'tmustangaew@icloud.com',
        ],
    ];

    public function safeUp(): bool
    {
        // Resolve every class first so a missing entry aborts before anything is saved.
        $entries = [];
        foreach (array_keys(self::EMAILS_BY_CLASS_SLUG) as $slug) {
            $entry = Entry::find()
                ->section('classes')
                ->slug($slug)
                ->status(null)
                ->one();

            if (!$entry) {
                echo "    > Class entry not found for slug \"$slug\"\n";

                return false;
            }

            $entries[$slug] = $entry;
        }

        foreach ($entries as $slug => $entry) {
            $rows = array_map(fn (string $email) => ['col1' => $email], self::EMAILS_BY_CLASS_SLUG[$slug]);
            $entry->setFieldValue('notificationEmails', $rows);

            if (!Craft::$app->getElements()->saveElement($entry)) {
                echo "    > Failed to save \"{$entry->title}\": " . implode(', ', $entry->getFirstErrors()) . "\n";

                return false;
            }

            echo "    > Set " . count($rows) . " notification emails on \"{$entry->title}\"\n";
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m260918_000000_class_notification_emails_2026 cannot be reverted.\n";

        return false;
    }
}
