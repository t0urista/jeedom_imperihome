<?php
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}

?>

<a class="btn btn-default btn-xs" id="bt_selectAllISS"><i class="fa fa-check-square-o"></i> Sélectionner tout</a>
<a class="btn btn-default btn-xs" id="bt_unselectAllISS"><i class="fa"></i> Désélectionner tout</a>
<a class="btn btn-success pull-right bt_saveISSConfig" id=""><i class="fa fa-floppy-o"></i> Sauvegarder</a><br>

<br>
<table class="table table-bordered table-condensed tablesorter" id="cmdList">
    <thead>
        <tr>
            <th>{{Objet}}</th>
            <th>{{Equipement}}</th>
            <th>{{Type}}</th>
            <th>{{Commande}}</th>
        </tr>
    </thead>
    <tbody>
        <?php
foreach (eqLogic::all() as $eqLogic) {
	if ($eqLogic->getIsEnable() == 0 || $eqLogic->getIsVisible() == 0) {
		continue;
	}
	$object = $eqLogic->getObject();
	if (is_object($object) && $object->getIsVisible() == 0) {
		continue;
	}
	echo '<tr>';
	echo '<td>';
	if (is_object($object)) {
		echo $object->getName();
	} else {
		echo __('Aucun', __FILE__);
	}
	echo '</td>';
	echo '<td>';
	echo $eqLogic->getName();
	echo '</td>';
	echo '<td>';
	echo $eqLogic->getEqType_name();
	echo '</td>';

	echo '<td>';
	echo '<table class="table table-bordered table-condensed">';
	echo '<thead>';
	echo '<tr>';
	echo '<td>{{Nom}}</td>';
	echo '<td>{{Transmettre}}</td>';
	echo '<td>{{Type Imperihome}}</td>';
	echo '</tr>';
	echo '</thead>';
	echo '<tbody>';
	foreach ($eqLogic->getCmd() as $cmd) {
		if ($cmd->getIsVisible() == 0) {
			continue;
		}
		echo '<tr class="imperihome" data-cmd_id="' . $cmd->getId() . '">';
		echo '<td>';
		echo $cmd->getName();
		echo '</td>';
		echo '<td>';
		echo '<input type="checkbox" class="imperihomeAttr" data-l1key="cmd_transmit" />';
		echo '</td>';
		echo '<td>';
		echo '</td>';
		echo '</tr>';
	}

	echo '</tbody>';

	echo '</table>';
	echo '</td>';

	echo '</tr>';
}
?>
    </tbody>
</table>


<?php include_file('desktop', 'imperihome', 'js', 'imperihome');?>