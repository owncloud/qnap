<?php
/**
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2020, ownCloud GmbH
 * @license GPL-2.0
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
/** @var OC_Theme $theme */
p($l->t('Dear ownCloud - Admin,'));
print_unescaped("\n");
print_unescaped("\n");
p($l->t('The maximum user limit was exceeded.'));
print_unescaped("\n");
p($l->t('To add further users, purchase a license from '));
print_unescaped("\n");
print_unescaped("https://software.qnap.com/owncloud.html");
print_unescaped("\n");
print_unescaped("\n");
// TRANSLATORS term at the end of a mail
p($l->t("Cheers!"));
print_unescaped("\n");
?>

--
<?php p($theme->getName() . ' - ' . $theme->getSlogan()); ?>
<?php print_unescaped("\n".$theme->getBaseUrl());
