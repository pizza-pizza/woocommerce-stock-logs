(function($) {
	var $wp_inline_edit = inlineEditPost.edit;

	inlineEditPost.edit = function( id ) {
		$wp_inline_edit.apply( this, arguments );

		var pid = $('#stock_adjustment_inputs').parents('tr').attr('id').replace('edit-','');
		$('#stock_adjustment_inputs').html('<img src="images/loading.gif" />').load(ajaxurl + '?action=render_wcstock_quickedit',{post_ID:pid});
	}
})(jQuery);