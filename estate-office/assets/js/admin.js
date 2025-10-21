(function ( $, wp ) {
    'use strict';

    const EstateOfficeMedia = {
        init() {
            $( document ).on( 'click', '.estate-office-select-media', this.openFrame.bind( this ) );
            $( document ).on( 'click', '.estate-office-remove-media', this.clearField.bind( this ) );
        },

        openFrame( event ) {
            event.preventDefault();

            const button = $( event.currentTarget );
            const targetField = $( `#${ button.data( 'target' ) }` );
            const previewId = `${ button.data( 'target' ) }-preview`;

            if ( ! targetField.length ) {
                return;
            }

            const frame = wp.media({
                title: EstateOfficeAdmin.i18n.selectImage,
                button: {
                    text: EstateOfficeAdmin.i18n.useImage
                },
                multiple: false
            });

            frame.on( 'select', () => {
                const attachment = frame.state().get( 'selection' ).first().toJSON();
                targetField.val( attachment.id );

                const preview = $( `#${ previewId }` );
                if ( preview.length ) {
                    preview.attr( 'src', attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url );
                    preview.show();
                }
            });

            frame.open();
        },

        clearField( event ) {
            event.preventDefault();

            const button = $( event.currentTarget );
            const targetField = $( `#${ button.data( 'target' ) }` );
            const preview = $( `#${ button.data( 'target' ) }-preview` );

            targetField.val( '' );
            if ( preview.length ) {
                preview.attr( 'src', '' ).hide();
            }
        }
    };

    const EstateOfficeDynamicLists = {
        init() {
            $( document ).on( 'click', '.estate-office-add-row', this.addRow );
            $( document ).on( 'click', '.estate-office-remove-row', this.removeRow );
        },

        addRow( event ) {
            event.preventDefault();

            const button = $( event.currentTarget );
            const templateId = button.data( 'template' );
            const template = $( `#${ templateId }` ).html();
            const list = button.closest( '.estate-office-card' ).find( '.estate-office-dynamic-list' );

            if ( template && list.length ) {
                list.append( template );
            }
        },

        removeRow( event ) {
            event.preventDefault();
            const row = $( event.currentTarget ).closest( '.estate-office-dynamic-item' );
            row.remove();
        }
    };

    $( () => {
        if ( typeof wp !== 'undefined' && wp.media && typeof EstateOfficeAdmin !== 'undefined' ) {
            EstateOfficeMedia.init();
        }
        EstateOfficeDynamicLists.init();
    } );
})( jQuery, window.wp );
