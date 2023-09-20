

$('#selectHK').on('change', function () {
	$.ajax({
		type: 'POST',
		url: 'plugins/velux/core/ajax/velux.ajax.php',
		data: {
			action: 'getCmdAssociationPropositions',
			hkEq_id: $(this).val(),
		},
		dataType: 'json',
		global: false,
		error: function(request, status, error) {
			handleAjaxError(request, status, error)
		},
		success: function(data) {
			if (data.state != 'ok') {
				$.fn.showAlert({message: data.result, level: 'danger'})
				return
			}
			cmds = json_decode(data.result)
			for (logicalId of [
				'refresh',
				'identify',
				'target_info',
				'target_action',
				'position',
				'state'
			]) {
				options = ""
				activ = ""
				for (cmd of cmds[logicalId]) {
					selected= ''
					if (cmd.selected == 1) {
						activ = cmd.id
					}
					options += '<option value="' + cmd.id + '" humanName="' + cmd.humanName + '">'
					options += cmd.name
					options += '</option>'
				}
				$('.hkCmdSelector[data-logicalId="' + logicalId + '"]').empty().append(options).val(activ)
			}
		}
	})
})

function modal_selectHK_getResult() {
	result={}
	result2save={}
	$('.hkCmdSelector').each(function() {
		logicalId = $(this).attr('data-logicalId')
		id = $(this).val()
		humanName = $(this).find('option:selected').attr('humanName')
		result2save[logicalId] = id
		result[logicalId] = {}
		result[logicalId]['humanName'] = humanName
		result[logicalId]['id'] = id
	})
	$.ajax({
		type: 'POST',
		url: 'plugins/velux/core/ajax/velux.ajax.php',
		data: {
			action: 'saveHkCmdSelections',
			hkEq_id: $('#selectHK').val(),
			values: result2save,
		},
		dataType: 'json',
		global: false,
		error: function(request, status, error) {
			handleAjaxError(request, status, error)
		},
		success: function(data) {
			if (data.state != 'ok') {
				$.fn.showAlert({message: data.result, level: 'danger'})
				return
			}
			return
		}
	})
	return result
}
