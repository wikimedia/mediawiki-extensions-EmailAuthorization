const EmailAuthorizationConfig = ( function () {
	'use strict';
	return {
		initializeAuthorized: function () {
			$( '#authorized-table' ).DataTable( {
				serverSide: true,
				processing: true,
				language: {
					loadingRecords: '&nbsp;',
					processing: '<div class="spinner"></div>'
				},
				searchDelay: 800,
				ajax: this.getAuthorizedData,
				columns: [
					{
						data: 'email',
						title: mw.message( 'emailauthorization-config-label-email' ).text()
					},
					{
						data: 'userNames',
						render: {
							display: function ( data, type, row, meta ) {
								return data.join( '<br />' );
							}
						},
						title: mw.message( 'emailauthorization-config-label-username' ).text()
					},
					{
						data: 'realNames',
						render: {
							display: function ( data, type, row, meta ) {
								return data.join( '<br />' );
							}
						},
						title: mw.message( 'emailauthorization-config-label-realname' ).text()
					},
					{
						data: 'userPages',
						title: mw.message( 'emailauthorization-config-label-userpage' ).text(),
						orderable: false,
						searchable: false
					}
				]
			} );
		},
		initializeAllUsers: function () {
			$( '#user-table' ).DataTable( {
				serverSide: true,
				processing: true,
				language: {
					loadingRecords: '&nbsp;',
					processing: '<div class="spinner"></div>'
				},
				searchDelay: 800,
				ajax: this.getAllUsersData,
				columns: [
					{
						data: 'email',
						title: mw.message( 'emailauthorization-config-label-email' ).text()
					},
					{
						data: 'userName',
						title: mw.message( 'emailauthorization-config-label-username' ).text()
					},
					{
						data: 'realName',
						title: mw.message( 'emailauthorization-config-label-realname' ).text()
					},
					{
						data: 'userPage',
						title: mw.message( 'emailauthorization-config-label-userpage' ).text(),
						orderable: false,
						searchable: false
					},
					{
						data: 'authorized',
						title: mw.message( 'emailauthorization-config-label-authorized' ).text(),
						orderable: false,
						searchable: false,
						className: 'dt-center'
					}
				]
			} );
		},
		getAuthorizedData: function ( data, callback, settings ) {
			const api = new mw.Api();
			api.get( {
				action: 'emailauthorization-getauthorized',
				draw: data.draw,
				offset: data.start,
				limit: data.length,
				search: data.search.value,
				columns: data.columns.map( ( c ) => c.data ).join( '|' ),
				order: data.order.map( ( o ) => o.column.toString() + o.dir ).join( '|' )
			} ).done( ( response ) => {
				callback( response[ 'emailauthorization-getauthorized' ] );
			} ).fail( ( response ) => {
				callback( {
					draw: data.draw,
					recordsTotal: 0,
					recordsFiltered: 0,
					data: [],
					error: response
				} );
			} );
		},
		getAllUsersData: function ( data, callback, settings ) {
			const api = new mw.Api();
			api.get( {
				action: 'emailauthorization-getall',
				draw: data.draw,
				offset: data.start,
				limit: data.length,
				search: data.search.value,
				columns: data.columns.map( ( c ) => c.data ).join( '|' ),
				order: data.order.map( ( o ) => o.column.toString() + o.dir ).join( '|' )
			} ).done( ( response ) => {
				callback( response[ 'emailauthorization-getall' ] );
			} ).fail( ( response ) => {
				callback( {
					draw: data.draw,
					recordsTotal: 0,
					recordsFiltered: 0,
					data: [],
					error: response
				} );
			} );
		}
	};
}() );

( function () {
	$( () => {
		if ( mw.config.exists( 'EmailAuthorizationAuthorizedData' ) ) {
			EmailAuthorizationConfig.initializeAuthorized();
		} else if ( mw.config.exists( 'EmailAuthorizationUserData' ) ) {
			EmailAuthorizationConfig.initializeAllUsers();
		}
	} );
}() );

window.EmailAuthorizationConfig = module;
