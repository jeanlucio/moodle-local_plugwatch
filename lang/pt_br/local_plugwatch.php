<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Strings em Português do Brasil para local_plugwatch.
 *
 * @package    local_plugwatch
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// phpcs:disable moodle.Files.LineLength

defined('MOODLE_INTERNAL') || die();

$string['capability_use'] = 'Usar Monitor de Plugins';
$string['capability_use_help'] = 'Permite ao usuário gerenciar uma lista pessoal de plugins Moodle para monitorar e receber notificações de atualização em seu próprio idioma.';
$string['errorlimitreached'] = 'Você atingiu o limite máximo de {$a} plugins vigiados.';
$string['errorpluginnotfound'] = 'Plugin não encontrado no Diretório de Plugins.';
$string['messageprovider_plugin_updated'] = 'Atualização de plugin disponível';
$string['noaisummary'] = 'Resumo por IA não disponível.';
$string['notification_body'] = 'Uma nova versão de {$a->name} ({$a->component}) está disponível: {$a->release}.

{$a->summary}

Ver no Diretório de Plugins: {$a->link}';
$string['notification_subject'] = 'Atualização de plugin: {$a->name} {$a->release}';
$string['pluginname'] = 'Monitor de Plugins';
$string['privacy_items_purpose'] = 'Armazena a lista de plugins que o usuário escolheu monitorar.';
$string['privacy_state_purpose'] = 'Armazena a última versão conhecida e o timestamp da última notificação para cada plugin vigiado.';
$string['task_check_updates'] = 'Verificar atualizações de plugins';
