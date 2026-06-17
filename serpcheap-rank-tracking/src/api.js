import apiFetch from '@wordpress/api-fetch';

const base = '/serpcheap/v1';

export const listTrackers = () => apiFetch( { path: `${ base }/trackers` } );

export const createTracker = ( data ) =>
	apiFetch( { path: `${ base }/trackers`, method: 'POST', data } );

export const updateSchedule = ( id, data ) =>
	apiFetch( { path: `${ base }/trackers/${ id }`, method: 'PATCH', data } );

export const refreshTracker = ( id ) =>
	apiFetch( { path: `${ base }/trackers/${ id }/refresh`, method: 'POST' } );

export const deleteTracker = ( id ) =>
	apiFetch( { path: `${ base }/trackers/${ id }`, method: 'DELETE' } );

export const getAlerts = () => apiFetch( { path: `${ base }/alerts` } );

export const markAlertsRead = ( id ) =>
	apiFetch( { path: `${ base }/alerts/read`, method: 'POST', data: id ? { id } : {} } );
