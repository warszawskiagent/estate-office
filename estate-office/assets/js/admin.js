(function ($) {
'use strict';

function initMediaField($field) {
var frame;
var target = $('#' + $field.data('target'));

$field.on('click', '.estateoffice-media-upload', function (event) {
event.preventDefault();

if (frame) {
frame.open();
return;
}

frame = wp.media({
title: event.currentTarget.innerText,
button: {
text: event.currentTarget.innerText,
},
multiple: false,
});

frame.on('select', function () {
var attachment = frame.state().get('selection').first().toJSON();
target.val(attachment.id);
$field.find('.estateoffice-media-preview').html('<img src="' + attachment.sizes.thumbnail.url + '" alt="" />');
});

frame.open();
});

$field.on('click', '.estateoffice-media-remove', function (event) {
event.preventDefault();
target.val('');
$field.find('.estateoffice-media-preview').html('<span>' + estateOfficeAdmin.i18n.noFile + '</span>');
});
}

function initRepeater($repeater) {
$repeater.on('click', '.estateoffice-repeater-add', function (event) {
event.preventDefault();
var $items = $repeater.find('.estateoffice-repeater-items');
var fieldName = $items.find('input:first').attr('name');
var $row = $('<div class="estateoffice-repeater-item"></div>');
$row.append('<input type="text" class="regular-text" name="' + fieldName + '" />');
$row.append('<button type="button" class="button link-delete estateoffice-repeater-remove">' + estateOfficeAdmin.i18n.removeField + '</button>');
$items.append($row);
});

$repeater.on('click', '.estateoffice-repeater-remove', function (event) {
event.preventDefault();
$(this).closest('.estateoffice-repeater-item').remove();
});

$repeater.find('.estateoffice-repeater-item').each(function () {
var $item = $(this);
if (0 === $item.find('.estateoffice-repeater-remove').length) {
$item.append('<button type="button" class="button link-delete estateoffice-repeater-remove">' + estateOfficeAdmin.i18n.removeField + '</button>');
}
});
}

$(function () {
$('.estateoffice-media-field').each(function () {
initMediaField($(this));
});

$('.estateoffice-repeater').each(function () {
initRepeater($(this));
});
});
})(jQuery);
