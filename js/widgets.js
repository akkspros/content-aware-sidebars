/*!
 * @package Content Aware Sidebars
 * @author Joachim Jensen <jv@intox.dk>
 * @license GPLv3
 * @copyright 2017 by Joachim Jensen
 */

(function($) {

	var cas_widgets = {

		$sidebarContainer: $(".widget-liquid-right"),
		$widgetContainer: $('#available-widgets'),
		$widgets:null,

		/**
		 * Initiate
		 *
		 * @since  3.0
		 * @return {void}
		 */
		init: function() {

			this.addSidebarToolbar();
			this.addWidgetSearch();
			this.toggleSidebarStatus();

			var $widget_list = $('#widget-list');

			$('div.widgets-sortables')
			.on('sortstart',function(e,ui) {
				console.log("YO");
				$widget_list.css('overflow-y','visible');
			})
			.on('sortstop',function(e,ui) {
				$widget_list.css('overflow-y','auto');
			});

			
			$('#widget-list').children('.widget')
			.on('dragstart',function(e,ui) {
				$widget_list.css('overflow-y','visible');
			})
			.on('dragstop',function(e,ui) {
				$widget_list.css('overflow-y','auto');
			});

		},

		/**
		 * Initiate
		 *
		 * @since  3.3
		 * @return {void}
		 */
		toggleSidebarStatus: function() {
			$(".widget-liquid-right").on('change','.sidebar-status-input',function(e) {
				var $this = $(this),
					status = $this.is(':checked');

				if(!($this.hasClass('sidebar-status-future') && !confirm(CASAdmin.enableConfirm))) {
					$.post(
					    ajaxurl, 
					    {
							'action'    : 'cas_sidebar_status',
							'sidebar_id': $this.val(),
							'status'    : status
					    }, 
					    function(response){
					    	if(response.success) {
					    		//change title attr
					    		$this.next().attr('title',response.data.title);
					    		$this.removeClass('sidebar-status-future');
					    	} else {
					    		$this.attr('checked',!status);
					    	}
					    }
					);
				} else {
					$this.attr('checked',!status);
				}
			});
		},
		/**
		 * Add search input for widgets
		 *
		 * @since 3.0
		 */
		addWidgetSearch: function() {
			this.$widgets = $(".widget",this.$widgetContainer).get().reverse();
			$(".sidebar-description",this.$widgetContainer).prepend('<input type="search" class="js-cas-widget-filter cas-filter-widget" placeholder="'+CASAdmin.filterWidgets+'...">');
			this.searchWidgetListener();
		},
		/**
		 * Listen to widget filter
		 *
		 * @since  3.0
		 * @return {void}
		 */
		searchWidgetListener: function() {
			var that = this,
				filterTimer,
				cachedFilter = "";
			this.$widgetContainer.on('input', '.js-cas-widget-filter',function(e) {
				var filter = $(this).val();
				if(filter != cachedFilter) {
					cachedFilter = filter;
					if( filterTimer ) {
						clearTimeout(filterTimer);
					}
					filterTimer = setTimeout(function(){
						$(that.$widgets).each(function(key,widget) {
							var $widget = $(widget);
							if ($widget.find(".widget-title :nth-child(1)").text().search(new RegExp(filter, "i")) < 0) {
								$widget.fadeOut();
							} else {
								//CSS dependent on order, so move to top
								$widget.prependTo($widget.parent());
								$widget.fadeIn().css("display","");
							}
						});
					}, 250);
				}
			});
		},
		/**
		 * Add toolbar for sidebars
		 *
		 * @since 3.0
		 */
		addSidebarToolbar: function() {

			var box = '<div class="wp-filter cas-filter-sidebar">'+
			'<a href="admin.php?page=wpcas-edit" class="button button-primary">'+CASAdmin.addNew+'</a>'+
			'<input type="search" class="js-cas-filter" placeholder="'+CASAdmin.filterSidebars+'...">'+
			'<a href="#" title="'+CASAdmin.collapse+'" class="js-sidebars-toggle sidebars-toggle" data-toggle="0"><span class="dashicons dashicons-arrow-up-alt2"></span></a>'+
			'<a href="#" title="'+CASAdmin.expand+'" class="js-sidebars-toggle sidebars-toggle" data-toggle="1"><span class="dashicons dashicons-arrow-down-alt2"></span></a>'+
			'</div>';

			this.$sidebarContainer.prepend(box);
			this.searchSidebarListener();
			this.addSidebarToggle();

		},

		/**
		 * Toggle all sidebars
		 *
		 * @since 3.3
		 */
		addSidebarToggle: function() {
			var $document = $(document),
				$sidebars = this.$sidebarContainer.find('.widgets-holder-wrap');
			$('body').on('click','.js-sidebars-toggle', function(e) {
				e.preventDefault();
				
				var open = !!$(this).data("toggle");

				$sidebars
				.toggleClass('closed',!open);
				if(open) {
					$sidebars.children('.widgets-sortables').sortable('refresh');
				}

				$document.triggerHandler('wp-pin-menu');
				
				//$sidebars.click();
			})
		},
		/**
		 * Listen to sidebar filter
		 *
		 * @since  3.0
		 * @return {void}
		 */
		searchSidebarListener: function() {
			var that = this,
				filterTimer,
				cachedFilter = "";
			this.$sidebarContainer.on('input', '.js-cas-filter',function(e) {
				var filter = $(this).val();
				if(filter != cachedFilter) {
					cachedFilter = filter;
					if( filterTimer ) {
						clearTimeout(filterTimer);
					}
					filterTimer = setTimeout(function(){
						$(".widgets-holder-wrap",that.$sidebarContainer).each(function(key,sidebar) {
							var $sidebar = $(sidebar);
							if ($sidebar.find(".sidebar-name :nth-child(2)").text().search(new RegExp(filter, "i")) < 0) {
								$sidebar.fadeOut();
							} else {
								$sidebar.fadeIn();
							}
						});
					}, 250);
				}
			});
		}

	};

	$(document).ready(function(){
		cas_widgets.init();
	});

})(jQuery);
