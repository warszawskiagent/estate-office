(function($){
'use strict';

function withNonce() {
if (!window.wp || !wp.apiFetch) {
return;
}

const middlewares = wp.apiFetch.getMiddlewares();
const hasNonce = middlewares.some(function(middleware){
return middleware.name === 'estateOfficeNonce';
});

if (!hasNonce) {
wp.apiFetch.use(function(next){
return function(options){
options = options || {};
options.headers = options.headers || {};
options.headers['X-WP-Nonce'] = estateOffice.nonce;
return next(options);
};
}).name = 'estateOfficeNonce';
}
}

function initMediaUploaders() {
$(document).on('click', '.estate-office-media-upload', function (event) {
event.preventDefault();
const button = $(this);
const target = $('#' + button.data('target'));
const frame = wp.media({
title: button.text(),
multiple: false
});

frame.on('select', function () {
const attachment = frame.state().get('selection').first().toJSON();
target.val(attachment.id).trigger('change');
const preview = button.siblings('.estate-office-media-preview');
const thumb = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
preview.html('<img src="' + thumb + '" alt="" />');
});

frame.open();
});

$(document).on('click', '.estate-office-media-remove', function (event) {
event.preventDefault();
const button = $(this);
const target = $('#' + button.data('target'));
target.val('0');
button.siblings('.estate-office-media-preview').empty();
});
}

function initDynamicFields() {
$('.estate-office-dynamic-fields').each(function(){
const container = $(this);
container.on('click', '.estate-office-add-row', function(){
const table = container.find('tbody');
const index = table.find('tr').length;
const fieldName = container.data('field-name');
const row = $('<tr>');
row.append('<td><input type="text" name="' + fieldName + '[' + index + '][label]" value="" /></td>');
row.append('<td><input type="text" name="' + fieldName + '[' + index + '][key]" value="" /></td>');
let selectHtml = '<select name="' + fieldName + '[' + index + '][type]">';
['text', 'number', 'select', 'checkbox', 'date'].forEach(function(type){
selectHtml += '<option value="' + type + '">' + type.charAt(0).toUpperCase() + type.slice(1) + '</option>';
});
selectHtml += '</select>';
row.append('<td>' + selectHtml + '</td>');
row.append('<td><button type="button" class="button-link estate-office-remove-row">&times;</button></td>');
table.append(row);
});

container.on('click', '.estate-office-remove-row', function(){
$(this).closest('tr').remove();
});
});
}

function initAgentPrefill() {
const agentSelect = $('#user_id');
if (!agentSelect.length || !window.wp || !wp.apiFetch) {
return;
}

agentSelect.on('change', function(){
const userId = parseInt($(this).val(), 10);
if (!userId) {
$('#first_name').val('');
$('#last_name').val('');
$('#email').val('');
$('#phone').val('');
if (window.tinymce && tinymce.get('bio')) {
tinymce.get('bio').setContent('');
}
$('#avatar_id').val('0');
$('.estate-office-media-preview').empty();
return;
}

wp.apiFetch({
path: '/wp/v2/users/' + userId + '?context=edit'
}).then(function(response){
$('#first_name').val(response.first_name || '');
$('#last_name').val(response.last_name || '');
$('#email').val(response.email || '');
$('#phone').val(response.meta && response.meta.estate_office_phone ? response.meta.estate_office_phone : '');
if (window.tinymce && tinymce.get('bio')) {
tinymce.get('bio').setContent(response.meta && response.meta.estate_office_bio ? response.meta.estate_office_bio : '');
}
if (response.meta && response.meta.estate_office_avatar) {
$('#avatar_id').val(response.meta.estate_office_avatar);
wp.media.attachment(response.meta.estate_office_avatar).fetch().then(function(){
const attachment = wp.media.attachment(response.meta.estate_office_avatar).toJSON();
const thumb = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
$('.estate-office-media-preview').html(thumb ? '<img src="' + thumb + '" alt="" />' : '');
});
} else {
$('.estate-office-media-preview').empty();
}
}).catch(function(){
window.alert('Nie udało się pobrać danych agenta.');
});
});
}

$(function(){
withNonce();
initMediaUploaders();
initDynamicFields();
initAgentPrefill();
});
})(jQuery);
