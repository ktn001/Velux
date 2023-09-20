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
	prefix = 'w_'
    } else if (model == "External Cover") {
	modalTitle = "{{Sélection d'un store externe}}"
	inputId = "#hkStore"
	prefix = 's_'
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
	    $(this).dialog("close")
	    $(inputId).val("")
	}
    },
    {
	text: "{{Valider}}",
	click: function() {
	    $(this).dialog("close")
	    $(inputId).val("#" + $('#modal_selectHK #selectHK').text() + "#")
	    associations = modal_selectHK_getResult()
	    for (logical in associations) {
		if (logical == 'refresh') {
		} else {
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
    }])

    $('#modContainer_selectHK').dialog('open')
}

/* Click sur le bouton de choix de fenêtre HK */
$("#selectWindow").off('click').on('click',function () {
    selectHK('Window')
})

/* Click sur le bouton de choix de store HK */
$("#selectStore").off('click').on('click',function () {
    selectHK('External Cover')
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
  tr += '</td>'
  tr += '<td>'
  tr += '<div class="input-group">'
  tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom de la commande}}">'
  tr += '<span class="input-group-btn"><a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a></span>'
  tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding:0 5px 0 0!important;"></span>'
  tr += '</div>'
  tr += '</td>'
  tr += '<td>'
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="type" style="width:100px; margin-bottom:3px" disabled></input>'
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="subType" style="width:100px; margin-top:5px" disabled></input>'
  tr += '</td>'
  tr += '<td>'
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="logicalId" disabled></input>'
  tr += '</td>'
  tr += '<td>'
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="linkedCmd"></input>'
  tr += '</td>'
  tr += '<td>'
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/>{{Afficher}}</label> '
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" checked/>{{Historiser}}</label> '
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label> '
  tr += '<div style="margin-top:7px;">'
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="Unité" title="{{Unité}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
  tr += '</div>'
  tr += '</td>'
  tr += '<td>';
  tr += '<span class="cmdAttr" data-l1key="htmlstate"></span>';
  tr += '</td>';
  tr += '<td>'
  if (is_numeric(_cmd.id)) {
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> '
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>'
  }
  tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove" title="{{Supprimer la commande}}"></i></td>'
  tr += '</tr>'
  $('#table_cmd tbody').append(tr)
  var tr = $('#table_cmd tbody tr').last()
  jeedom.eqLogic.buildSelectCmd({
    id: $('.eqLogicAttr[data-l1key=id]').value(),
    filter: { type: 'info' },
    error: function (error) {
      $('#div_alert').showAlert({ message: error.message, level: 'danger' })
    },
    success: function (result) {
      tr.find('.cmdAttr[data-l1key=value]').append(result)
      tr.setValues(_cmd, '.cmdAttr')
      jeedom.cmd.changeType(tr, init(_cmd.subType))
    }
  })
}
