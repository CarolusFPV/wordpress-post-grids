( function( wp ) {
    var registerPlugin = wp.plugins.registerPlugin;
    var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
    var withSelect = wp.data.withSelect;
    var withDispatch = wp.data.withDispatch;
    var compose = wp.compose.compose;
    var createElement = wp.element.createElement;
    var __ = wp.i18n.__;

    var CpgIconPanel = function( props ) {
        // Retrieve the icons from the localized settings.
        var icons = cpgIconPanelSettings.icons || [];
        var selectedIcon = props.postIcon || '';
        return createElement(
            PluginDocumentSettingPanel,
            {
                title: __( 'Post Icon', 'polaris-core' ),
                icon: 'format-image',
                initialOpen: true
            },
            createElement(
                'label',
                { htmlFor: 'cpg_post_icon' },
                __( 'Choose an Icon', 'polaris-core' )
            ),
            createElement(
                'select',
                {
                    id: 'cpg_post_icon',
                    value: selectedIcon,
                    onChange: function( event ) {
                        props.setPostIcon( event.target.value );
                    },
                    style: { width: '100%', marginTop: '5px' }
                },
                createElement(
                    'option',
                    { value: '' },
                    __( 'None', 'polaris-core' )
                ),
                icons.map( function( icon ) {
                    var fileName = icon.split('/').pop();
                    return createElement(
                        'option',
                        { value: icon, key: icon },
                        fileName
                    );
                } )
            )
        );
    };

    var CpgIconPanelPlugin = compose(
        withSelect( function( select ) {
            var meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' );
            return { postIcon: meta._cpg_post_icon };
        } ),
        withDispatch( function( dispatch ) {
            return {
                setPostIcon: function( newValue ) {
                    dispatch( 'core/editor' ).editPost( { meta: { _cpg_post_icon: newValue } } );
                }
            };
        } )
    )( CpgIconPanel );

    registerPlugin( 'cpg-icon-panel', {
        render: CpgIconPanelPlugin,
        icon: 'format-image'
    } );
} )( window.wp );
