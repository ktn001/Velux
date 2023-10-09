/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* Sélection d'un équipement HK à associer */
function selectHK (model) {
    if ($('#modContainer_selectHK').length == 0) {
	$('body').append('<div id="modContainer_selectHK"></div>')
	jQuery.ajaxSetup({async: false})
	$('#modContainer_selectHK').load('index.php?v=d&plugin=velux&modal=selectHK')
	jQuery.ajaxSetup({async: true})
	$('#modContainer_selectHK').dialog({
	    closeText: '',
	    autoOpen: false,
	    modal: true,
	    height:400,
	    width: 400
	})
    }
    if (model == 'Window') {
	modalTitle = "{{Sélection d'une fenêtre}}"
	inputId = "#hkWindow"
	prefix = 'w:'
    } else if (model == "External Cover") {
	modalTitle = "{{Sélection d'un store externe}}"
	inputId = "#hkStore"
	prefix = 's:'
    } else {
	modalTitle = "{{ERREUR}}"
    }
    options = ""
    for (eq of hkEq[model]) {
	options += "<option value='" + eq.id + "'>" + eq.humanName + "</options>"
    }
    $("#modal_selectHK #selectHK").empty().append(options).trigger('change')
    $('#modContainer_selectHK').dialog({'title': modalTitle})
    $('#modContainer_selectHK').dialog('option', 'buttons', [{
	text: "{{Annuler}}",
	click: function() {
	    $(this).dialog("close")
	}
    },
    {
	text: "{{Supprimer}}",
	class: "btn-delete",
	click: function() {
	    msg = "{{Les commandes suivantes seront supprimées lors de la sauvagarde de l'équipement:}}<br>"
	    msg += '<ul>'
	    associations = modal_selectHK_getResult()
	    for (logical in associations) {
		if(logical != 'refresh') {
		    logicalId = prefix + logical
		    $('#table_cmd [data-l1key="logicalId"]').each(function() {
			if ($(this).val() == logicalId) {
			    msg += '<li>'
			    msg += $(this).closest('tr').find('[data-l1key="name"]').val()
			    msg += ' [' + logicalId + ']</li>'
			}
		    })
		}
	    }
	    msg += '</ul>'
	    bootbox.confirm({
		title : " ",
		message: msg,
		callback: function(result) {
		    if (result) {
			$('#modContainer_selectHK').dialog("close")
			$(inputId).val("")
			for (logical in associations) {
			    if (logical != 'refresh') {
				logicalId = prefix + logical
				$('#table_cmd [data-l1key="logicalId"]').each(function() {
				    if ($(this).val() == logicalId) {
					$(this).closest('tr').find('[data-l1key="configuration"][data-l2key="linkedCmd"]').val("")
					return false
				    }
				})
			    }
			}
		    }
		}
	    })
	}
    },
    {
	text: "{{Valider}}",
	click: function() {
	    $(this).dialog("close")
	    $(inputId).val("#" + $('#modal_selectHK #selectHK').text() + "#")
	    associations = modal_selectHK_getResult()
	    cmdCount = $('#table_cmd [data-l1key="logicalId"]').filter(function() { return $(this).val().startsWith(prefix) }).length
	    if ( cmdCount < Object.keys(associations).length ) {
		$.ajax({
		    type: 'POST',
		    url: 'plugins/velux/core/ajax/velux.ajax.php',
		    data: {
			action: 'getCmdConfigs'
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
			configs = json_decode(data.result)
			console.debug(configs)
			for (logicalId in configs) {
			    if (logicalId.startsWith(prefix)) {
				console.log("Adding " + logicalId)
				addCmdToTable(configs[logicalId])
			    }
			}
			setLinkedCmd (associations, prefix)
			return
		    }
		})
	    }
	    setLinkedCmd (associations, prefix)
	}
    }])

    $('#modContainer_selectHK').dialog('open')
}

function setLinkedCmd (associations, prefix) {
    for (logical in associations) {
	if (logical != 'refresh') {
	    logicalId = prefix + logical
	    $('#table_cmd [data-l1key="logicalId"]').each(function() {
		if ($(this).val() == logicalId) {
		    value = '#' + associations[logical]['humanName'] + '#'
		    $(this).closest('tr').find('[data-l1key="configuration"][data-l2key="linkedCmd"]').val(value)
		    return false
		}
	    })
	}
    }
}

/* Click sur le bouton de choix de fenêtre HK */
$("#selectWindow").off('click').on('click',function () {
    selectHK('Window')
})

/* Click sur le bouton de choix de store HK */
$("#selectStore").off('click').on('click',function () {
    selectHK('External Cover')
})

/* Click sur le bouton d'ajout d'une commande "cible" */
$(".cmdAction[data-action=add_target]").off('click').on('click',function () {
  addCmdToTable({
    type : "action",
    subType : "other",
    logicalId : "target"
  })
})

/* Click sur le bouton de séction d'info pour calcul */
$('#table_cmd tbody').on('click','.listEquipementInfo', function () {
  var el = $(this)
  jeedom.cmd.getSelectModal({ cmd: { type: 'info'} }, function (result) {
    var calcul = el.closest('tr').find('.cmdAttr[data-l1key="configuration"][data-l2key="' + el.data('input') + '"]')
    calcul.atCaret('insert', result.human)
  })
})

/* Permet la réorganisation des commandes dans l'équipement */
$("#table_cmd").sortable({
  axis: "y",
  cursor: "move",
  items: ".cmd",
  placeholder: "ui-state-highlight",
  tolerance: "intersect",
  forcePlaceholderSize: true
})

/* Fonction permettant l'affichage des commandes dans l'équipement */
function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = { configuration: {} }
  }
  if (!isset(_cmd.configuration)) {
    _cmd.configuration = {}
  }
  var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">'
  tr += '<td class="hidden-xs">'
  tr += '<span class="cmdAttr" data-l1key="id"></span>'
  tr += '<span class="cmdAttr hidden" data-l1key="configuration" data-l2key="order"></span>'
  tr += '</td>'
  tr += '<td>'
  tr += '<div class="input-group">'
  tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom de la commande}}">'
  tr += '<span class="input-group-btn"><a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a></span>'
  tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding:0 5px 0 0!important;"></span>'
  tr += '</div>'
  tr += '</td>'
  tr += '<td>'
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="type" style="margin-bottom:3px" disabled></input>'
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="subType" style="margin-top:5px" disabled></input>'
  tr += '</td>'
  tr += '<td>'
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="logicalId" disabled></input>'
  tr += '</td>'
  tr += '<td>'
  if (('logicalId' in _cmd) && _cmd.logicalId.includes(':')){
    tr += '<label style="font-weight:400;width:100%">{{Commande HK}}:' 
    tr +=   '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="linkedCmd"></input>'
    tr += '</label>'
  }
  if (('logicalId' in _cmd) && (_cmd.logicalId == 'pause')){
    tr += '<label style="font-weight:400;width:100%">{{Durée}}:' 
    tr +=   '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="returnStateTime"></input>'
    tr +=   '<span class="cmdAttr hidden" data-l1key="configuration" data-l2key="returnStateValue">0</span>'
    tr += '</label>'
  }
  if (('logicalId' in _cmd) && (_cmd.logicalId == 'target')){
    tr += '<label style="font-weight:400;max-width:48%">{{Position fenêtre}}:' 
    tr +=   '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="w:target"></input>'
    tr += '</label>'
    tr += '<label style="font-weight:400;max-width:48%; margin-left:2px">{{Position store}}:' 
    tr +=   '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="s:target"></input>'
    tr += '</label>'
  }
  if (('logicalId' in _cmd) && (_cmd.logicalId == 'rain')){
    tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="calcul" style="height:35px" placeHolder="{{Calcul"}}></textarea>'
    tr += '<a class="btn btn-default listEquipementInfo btn-xs" data-input="calcul" style="width:100%;margin-top:2px"><i calss="fas fa-list-alt"></i> {{Rechercher équipement}}</a>'
  }
  tr += '</td>'
  tr += '<td>'
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/>{{Afficher}}</label> '
  if (_cmd.type == 'info') {
    tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" checked/>{{Historiser}}</label> '
    tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label> '
  }
  if (['slider','numeric'].includes(_cmd.subType)) {
    tr += '<div style="margin-top:7px;">'
    if (('logicalId' in _cmd ) && (_cmd.logicalId.endsWith(':state'))) {
      tr += '<input class="tootip cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width30%;max-width:80px;display:inline-block;margin-right:2px">'
      tr += '<input class="tootip cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width30%;max-width:80px;display:inline-block">'
    } else {
      tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="Unité" title="{{Unité}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
    }
    tr += '</div>'
  }
  tr += '</td>'
  tr += '<td>';
  tr += '<span class="cmdAttr" data-l1key="htmlstate"></span>';
  tr += '</td>';
  tr += '<td>'
  if (is_numeric(_cmd.id)) {
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> '
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>'
  }
  if (('logicalId' in _cmd) && (_cmd.logicalId == 'target')){
    tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>'
  }
  tr += '</td>'
  tr += '</tr>'
  posPreset=_cmd.configuration.order
  if (posPreset == undefined) {
    posPreset = 0
  }
  posPreset = Number(posPreset)
  console.debug("posPreset: " + posPreset)
  inserted = false
  $('#table_cmd tbody tr [data-l1key="configuration"][data-l2key="order"]').each(function() {
    currentPreset = $(this).text()
    if (currentPreset == "") {
      currentPreset = 0
    }
    currentPreset = Number(currentPreset)
    console.log("currentPreset: " + currentPreset)
    if (currentPreset > posPreset) {
      $(this).closest('tr').before(tr)
      tr = $(this).closest('tr').prev()
      inserted = true
      return false
    }
  })
  if (! inserted) {
    $('#table_cmd tbody').append(tr)
    var tr = $('#table_cmd tbody tr').last()
  }
  tr.setValues(_cmd, '.cmdAttr')
}
