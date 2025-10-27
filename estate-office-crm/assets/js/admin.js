(function($){
'use strict';

function openMediaFrame(targetField){
const frame = wp.media({
title: EstateOfficeCRM.mediaFrameTitle,
button: { text: EstateOfficeCRM.mediaFrameTitle },
multiple: false
});

frame.on('select', function(){
const attachment = frame.state().get('selection').first().toJSON();
const idField = $('#' + targetField);
idField.val(attachment.id);
idField.trigger('change');
});

frame.open();
}

function refreshPreview(wrapper){
const fieldId = wrapper.data('target');
const preview = wrapper.find('.eo-crm-media-preview');
const attachmentId = $('#' + fieldId).val();

preview.empty();

if (!attachmentId){
return;
}

wp.media.attachment(attachmentId).fetch().then(function(){
const attachment = wp.media.attachment(attachmentId).toJSON();
if (attachment && attachment.sizes){
const url = attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
const img = $('<img>', { src: url, alt: attachment.alt || attachment.title });
preview.append(img);
}
});
}

function initialiseMediaControls(){
$('.eo-crm-media-control').each(function(){
const wrapper = $(this);
const target = wrapper.data('target');

wrapper.on('click', '.eo-crm-open-media', function(event){
event.preventDefault();
openMediaFrame(target);
});

$('#' + target).on('change', function(){
refreshPreview(wrapper);
});

refreshPreview(wrapper);
});
}

function initialiseDynamicFields(){
$('.eo-crm-dynamic-fields-wrapper').each(function(){
const wrapper = $(this);
const list = wrapper.find('.eo-crm-dynamic-fields-list');
let index = list.find('.eo-crm-dynamic-field').length;

wrapper.on('click', '.eo-crm-add-field', function(event){
event.preventDefault();
index++;
const fieldName = $(this).data('field-name');
const template = $(
'<div class="eo-crm-dynamic-field" data-index="' + index + '\">' +
'<p><label><span>' + wp.i18n.__('Klucz', 'estate-office-crm') + '</span>' +
'<input type="text" name="' + fieldName + '[' + index + '][key]" required></label></p>' +
'<p><label><span>' + wp.i18n.__('Etykieta', 'estate-office-crm') + '</span>' +
'<input type="text" name="' + fieldName + '[' + index + '][label]" required></label></p>' +
'<p><label><span>' + wp.i18n.__('Typ pola', 'estate-office-crm') + '</span>' +
'<select name="' + fieldName + '[' + index + '][type]">' +
'<option value="text">' + wp.i18n.__('Tekst', 'estate-office-crm') + '</option>' +
'<option value="number">' + wp.i18n.__('Liczba', 'estate-office-crm') + '</option>' +
'<option value="select">' + wp.i18n.__('Lista wyboru', 'estate-office-crm') + '</option>' +
'<option value="checkbox">' + wp.i18n.__('Checkbox', 'estate-office-crm') + '</option>' +
'<option value="textarea">' + wp.i18n.__('Pole tekstowe', 'estate-office-crm') + '</option>' +
'</select></label></p>' +
'<button type="button" class="button-link-delete eo-crm-remove-field" aria-label="' + wp.i18n.__('Usuń pole', 'estate-office-crm') + '">&times;</button>' +
'</div>'
);

list.append(template);
});

wrapper.on('click', '.eo-crm-remove-field', function(event){
event.preventDefault();
$(this).closest('.eo-crm-dynamic-field').remove();
});
});
}

$(function(){
initialiseMediaControls();
initialiseDynamicFields();
});

})(jQuery);
