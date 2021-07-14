<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
?>
<div id="qnap" class="section">
<h2 class="inlineblock"><?php p($l->t('User and License Information')); ?></h2>
<p>
	<?php p($l->t('ownCloud for QNAP comes as Community Edition by default and is free to use for up to 5 users.'))?>
	<br>
	<br>
	<?php print_unescaped($l->t('If you need more than 5 users or want to use any of our <a href="%s" target="_blank" rel="noreferrer">Enterprise Apps from the Marketplace</a>, you need to upgrade to Enterprise Edition by purchasing licenses from the <a href="%s" target="_blank" rel="noreferrer">QNAP Software Store</a>.', ['https://marketplace.owncloud.com/bundles/enterprise_apps', 'https://software.qnap.com/owncloud.html']))?>
	<br>
	<br>
	<?php p($l->t('Guest users don\'t count as normal users, that means you can invite as many as needed.'))?>
	<br>
	<br>
	<progress id="licensed-user-usage" value="<?php p($_['active_users']); ?>" max="<?php p($_['licensed_users']); ?>"></progress>
	<label for="licensed-user-usage"><?php p($l->t('Usage: %d of %d licensed users', [$_['active_users'], $_['licensed_users']]))?></label>
	<br>
</p>

<h3><?php p($l->t('License Overview')); ?></h3>
<table class="grid">
	<thead>
	<tr>
		<th scope="col"><?php p($l->t('License ID')); ?></th>
		<th scope="col"><?php p($l->t('Valid from')); ?></th>
		<th scope="col"><?php p($l->t('Valid until')); ?></th>
		<th scope="col"><?php p($l->t('Number of users')); ?></th>
		<th scope="col"><?php p($l->t('Status')); ?></th>
	</tr>
	</thead>
	<tbody>
	<?php foreach ($_['licenses'] as $lic): ?>
		<tr>
			<td><?php p($lic['license_id']); ?></td>
			<td><?php p($lic['license_info']['valid_from']->format(\DateTime::COOKIE)); ?></td>
			<td><?php p($lic['license_info']['valid_until']->format(\DateTime::COOKIE)); ?></td>
			<td><?php p($lic['license_info']['attributes']['owncloud_account']); ?></td>
			<td><?php p($lic['status']); ?></td>
		</tr>
	<?php endforeach ?>
	</tbody>
</table>
</div>
