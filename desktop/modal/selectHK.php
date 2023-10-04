<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
include_file('desktop', 'selectHK', 'css', 'velux');
?>
<div id="modal_selectHK">
	<div class="form-group">
		<label>{{Equipement Homekit}}:</label>
		<select id="selectHK" class="input-sm">
		<select>
	</div>
	<hr style="height:1px; background-color:var(--txt-color)">
	<div>
		<form class="form-horizontal">
			<div>
				<label class="col-sm-5 control-label input-sm">{{Identifier}}:</label>
				<select class="col-sm-7 input-sm hkCmdSelector" data-logicalId="identify">
				<select>
			</div>
			<div>
				<label class="col-sm-5 control-label input-sm">{{Position cible (info)}}:</label>
				<select class="col-sm-7 input-sm hkCmdSelector" data-logicalId="target_info">
				<select>
			</div>
			<div>
				<label class="col-sm-5 control-label input-sm">{{Position cible (action)}}:</label>
				<select class="col-sm-7 input-sm hkCmdSelector" data-logicalId="target_action">
				<select>
			</div>
			<div>
				<label class="col-sm-5 control-label input-sm">{{Position}}:</label>
				<select class="col-sm-7 input-sm hkCmdSelector" data-logicalId="position">
				<select>
			</div>
			<div>
				<label class="col-sm-5 control-label input-sm">{{Etat}}:</label>
				<select class="col-sm-7 input-sm hkCmdSelector" data-logicalId="state">
				<select>
			</div>
		</form>
	</div>
</div>

<?php
include_file('desktop', 'selectHK', 'js', 'velux');
?>
