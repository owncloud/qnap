<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
?>
<div id="qnap" class="section">
<h2 class="inlineblock"><?php p($l->t('ownCloud License on QNAP')); ?></h2>
<p>
	<?php p($l->t('Active users: %d', $_['active_users']))?>
	<br>
	<?php p($l->t('Licensed users: %d', $_['licensed_users']))?>
	<br>
	<a href="https://software.qnap.com/owncloud.html"><?php p($l->t('Visit the QNAP Store to purchase more licenses.')) ?></a>
</p>

<h3><?php p($l->t('License Overview')); ?></h3>
<table>
	<thead>
	<tr>
		<th scope="col"><?php p($l->t('License ID')); ?></th>
		<th scope="col"><?php p($l->t('Valid until')); ?></th>
		<th scope="col"><?php p($l->t('Number of users')); ?></th>
	</tr>
	</thead>
	<tbody>
	<?php foreach ($_['licenses'] as $lic): ?>
		<tr>
			<td><?php p($lic['license_id']); ?></td>
			<td><?php p($lic['license_info']['valid_until']->format(\DateTime::COOKIE)); ?></td>
			<td><?php p($lic['license_info']['attributes']['owncloud_account']); ?></td>
		</tr>
	<?php endforeach ?>
	</tbody>
</table>
</div>
