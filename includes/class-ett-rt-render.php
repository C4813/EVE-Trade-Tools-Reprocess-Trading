<?php
if (!defined('ABSPATH')) exit;

final class ETT_RT_Render {

    public static function render_profile(string $character_id): string {

        $character_id = (string) $character_id;
        if ($character_id === '' || !ctype_digit($character_id)) {
            return '<p>Invalid character.</p>';
        }

        $char_data = ETT_RT::get_character_data($character_id);

        if (empty($char_data['skill_levels']) && empty($char_data['standings'])) {
            return '<p>Error fetching data from ESI.</p>';
        }

        $skill_levels   = $char_data['skill_levels'];
        $standings_data = $char_data['standings'];

        /* Skills */
        $wanted_skills = ETT_RT::skill_ids();

        $connections      = $skill_levels[3359] ?? 0;
        $diplomacy        = $skill_levels[3357] ?? 0;
        $broker_relations = $skill_levels[3446] ?? 0;

        $out  = '<h4>Skills</h4>';
        $out .= '<table class="ett-table"><thead><tr><th>Skill</th><th>Level</th></tr></thead><tbody>';

        foreach ($wanted_skills as $id => $name) {
            $level = $skill_levels[$id] ?? 0;
            $out .= '<tr><td>' . esc_html($name) . '</td><td>' . esc_html((string)$level) . '</td></tr>';
        }

        $out .= '</tbody></table>';

        /* Standings */
        $hub_groups = [
            'Jita'    => ['faction' => 500001, 'corp' => 1000035],
            'Amarr'   => ['faction' => 500003, 'corp' => 1000086],
            'Rens'    => ['faction' => 500002, 'corp' => 1000049],
            'Dodixie' => ['faction' => 500004, 'corp' => 1000120],
            'Hek'     => ['faction' => 500002, 'corp' => 1000057],
        ];

        $entity_names = ETT_RT::entities();

        $out .= '<h4>Standings</h4>';
        $out .= '<table class="ett-table"><thead><tr>'
              . '<th>Trade Hub</th>'
              . '<th>Entity</th>'
              . '<th>Base</th>'
              . '<th title="' . esc_attr('=ROUND(baseCorpStanding+(10-baseCorpStanding)*(4%*connectionsSkill),2)') . '">Effective</th>'
              . '<th title="' . esc_attr("=ROUND(\n  IF(baseStanding=0,0.05,\n    LET(\n      rawEff,baseStanding+(10-baseStanding)*0.04*connectionsSkill,\n      eff,ROUND(rawEff,2),\n      IF(eff<=0,0.05,IF(eff<6.67,0.05*(1-eff/6.67),0))\n)),4)") . '">Reprocessing Tax</th>'
              . '<th title="' . esc_attr('=3%-(0.3%*brokerRelationsSkill)-(0.03%*baseFactionStanding)-(0.02%*baseCorpStanding)') . '">Brokerage Fee</th>'
              . '</tr></thead><tbody>';

        foreach ($hub_groups as $hub_name => $ids) {

            $faction_id = (int) $ids['faction'];
            $corp_id    = (int) $ids['corp'];

            $base_faction = round((float)($standings_data[$faction_id] ?? 0.0), 2);
            $base_corp    = round((float)($standings_data[$corp_id] ?? 0.0), 2);

            $base_faction = max(-10.0, min(10.0, $base_faction));
            $base_corp    = max(-10.0, min(10.0, $base_corp));

            $broker_fee = max(0.01, round(
                0.03 - (0.003 * $broker_relations) - (0.0003 * $base_faction) - (0.0002 * $base_corp),
                4
            ));

            if (abs($base_corp) < 0.00001) {
                $reproc_tax = 0.05;
            } else {
                $skill_for_tax = ($base_corp < 0.0) ? $diplomacy : $connections;
                $eff = round($base_corp + (10 - $base_corp) * (0.04 * $skill_for_tax), 2);

                if ($eff <= 0.0)     $reproc_tax = 0.05;
                elseif ($eff < 6.67) $reproc_tax = 0.05 * (1 - ($eff / 6.67));
                else                 $reproc_tax = 0.0;
            }

            $reproc_tax = round($reproc_tax, 4);
            $broker_fee = round($broker_fee, 4);

            $rows      = [$faction_id => $base_faction, $corp_id => $base_corp];
            $first_row = true;

            foreach ($rows as $entity_id => $base) {

                $entity_label = $entity_names[$entity_id] ?? ('ID ' . (int)$entity_id);

                if (abs($base) < 0.00001) {
                    $effective = 0.00;
                } else {
                    $skill     = ($base < 0.0) ? $diplomacy : $connections;
                    $effective = round($base + (10 - $base) * (0.04 * $skill), 2);
                }

                $out .= '<tr>';

                if ($first_row) {
                    $out .= '<td rowspan="2">' . esc_html($hub_name) . '</td>';
                }

                $out .= '<td>' . esc_html($entity_label) . '</td>';
                $out .= '<td>' . esc_html(number_format($base, 2, '.', '')) . '</td>';
                $out .= '<td>' . esc_html(number_format($effective, 2, '.', '')) . '</td>';

                if ($first_row) {
                    $out .= '<td rowspan="2">' . esc_html(number_format($reproc_tax * 100, 2, '.', '')) . '%</td>';
                    $out .= '<td rowspan="2">' . esc_html(number_format($broker_fee * 100, 2, '.', '')) . '%</td>';
                    $first_row = false;
                }

                $out .= '</tr>';
            }
        }

        $out .= '</tbody></table>';

        return $out;
    }
}
