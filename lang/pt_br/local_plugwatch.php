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

$string['addplugin'] = 'Adicionar plugin';
$string['capability_use'] = 'Usar Monitor de Plugins';
$string['capability_use_help'] = 'Permite ao usuário gerenciar uma lista pessoal de plugins Moodle para monitorar e receber notificações de atualização em seu próprio idioma.';
$string['errorlimitreached'] = 'Você atingiu o limite máximo de {$a} plugins vigiados.';
$string['errorpluginnotfound'] = 'Plugin não encontrado no Diretório de Plugins.';
$string['frequency'] = 'Frequência de notificação';
$string['frequency_daily'] = 'Diário';
$string['frequency_monthly'] = 'Mensal';
$string['frequency_weekly'] = 'Semanal';
$string['githubtoken'] = 'Token de API do GitHub (opcional)';
$string['githubtoken_help'] = 'Token de acesso pessoal para aumentar o limite de requisições da API do GitHub de 60 para 5000 por hora. Deixe em branco para usar acesso sem autenticação.';
$string['lastchecked'] = 'Última verificação';
$string['lastnotified'] = 'Última notificação';
$string['maxplugins'] = 'Máximo de plugins por usuário';
$string['maxplugins_help'] = 'Número máximo de plugins que cada usuário pode adicionar à sua lista de vigilância. Padrão: 30.';
$string['messageprovider_plugin_updated'] = 'Atualização de plugin disponível';
$string['noaisummary'] = 'Resumo por IA não disponível.';
$string['nopluginswatched'] = 'Você ainda não está vigiando nenhum plugin.';
$string['notification_body'] = 'Uma nova versão de {$a->name} ({$a->component}) está disponível: {$a->release}. {$a->summary} Ver no Diretório de Plugins: {$a->link}';
$string['notification_subject'] = 'Atualização de plugin: {$a->name} {$a->release}';
$string['plugin'] = 'Plugin';
$string['pluginname'] = 'Monitor de Plugins';
$string['pluginsearch'] = 'Buscar plugins';
$string['pluginsearch_placeholder'] = 'Nome ou componente do plugin (ex: block_xp)';
$string['preferences_heading'] = 'Monitor de Plugins — Preferências';
$string['privacy:metadata:github_api'] = 'O componente e repositório do plugin são enviados à API do GitHub para buscar as release notes. Nenhum dado pessoal do usuário é transmitido.';
$string['privacy:metadata:local_plugwatch_items'] = 'Armazena a lista de plugins que o usuário escolheu monitorar.';
$string['privacy:metadata:local_plugwatch_items:component'] = 'O nome Frankenstyle do plugin vigiado.';
$string['privacy:metadata:local_plugwatch_items:timecreated'] = 'A data em que o plugin foi adicionado à lista de vigilância.';
$string['privacy:metadata:local_plugwatch_items:userid'] = 'O ID do usuário que adicionou o plugin.';
$string['privacy:metadata:local_plugwatch_state'] = 'Armazena a última versão conhecida e o timestamp da última notificação para cada plugin vigiado.';
$string['privacy:metadata:local_plugwatch_state:component'] = 'O nome Frankenstyle do plugin vigiado.';
$string['privacy:metadata:local_plugwatch_state:releasename'] = 'O último release conhecido do plugin.';
$string['privacy:metadata:local_plugwatch_state:timechecked'] = 'A última vez em que a versão do plugin foi verificada.';
$string['privacy:metadata:local_plugwatch_state:timelastnotified'] = 'A última vez em que uma notificação sobre este plugin foi enviada.';
$string['privacy:metadata:local_plugwatch_state:timelastreleased'] = 'O timestamp do último release conhecido.';
$string['privacy:metadata:local_plugwatch_state:userid'] = 'O ID do usuário que está vigiando o plugin.';
$string['privacy:metadata:moodle_plugin_directory'] = 'Nomes de componentes de plugins são enviados à API do Diretório de Plugins do Moodle para obter metadados de versão. Nenhum dado pessoal do usuário é transmitido.';
$string['privacy_items_purpose'] = 'Armazena a lista de plugins que o usuário escolheu monitorar.';
$string['privacy_state_purpose'] = 'Armazena a última versão conhecida e o timestamp da última notificação para cada plugin vigiado.';
$string['releasenotes'] = 'Notas de versão';
$string['removeplugin'] = 'Remover';
$string['searchnoresults'] = 'Nenhum plugin encontrado para a busca informada.';
$string['settings_enabled'] = 'Habilitar Vigia de Plugins';
$string['settings_enabled_help'] = 'Quando desabilitado, nenhuma verificação é realizada e nenhuma notificação é enviada.';
$string['task_check_updates'] = 'Verificar atualizações de plugins';
$string['watchedplugins'] = 'Plugins vigiados';
$string['watchedplugins_count'] = 'Vigiando {$a->current} de {$a->max} plugins';
