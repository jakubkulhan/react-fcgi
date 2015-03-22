<?php
namespace React\Fcgi;

/**
 * FastCGI protocol constants.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class Constants
{

	/**
	 * Version to be used in frames.
	 */
	const VERSION = 1;

	/**
	 * BEGIN-REQUEST frame.
	 */
	const TYPE_BEGIN_REQUEST = 1;

	/**
	 * ABORT-REQUEST frame.
	 */
	const TYPE_ABORT_REQUEST = 2;

	/**
	 * END-REQUEST frame.
	 */
	const TYPE_END_REQUEST = 3;

	/**
	 * PARAMS frame.
	 */
	const TYPE_PARAMS = 4;

	/**
	 * STDIN frame.
	 */
	const TYPE_STDIN = 5;

	/**
	 * STDOUT frame.
	 */
	const TYPE_STDOUT = 6;

	/**
	 * STDERR frame.
	 */
	const TYPE_STDERR = 7;

	/**
	 * DATA frame.
	 */
	const TYPE_DATA = 8;

	/**
	 * GET-VALUES frame.
	 */
	const TYPE_GET_VALUES = 9;

	/**
	 * GET-VALUES-RESULT frame.
	 */
	const TYPE_GET_VALUES_RESULT = 10;

	/**
	 * UNKNOWN-TYPE frame.
	 */
	const TYPE_UNKNOWN_TYPE = 11;

	/**
	 * Null (connection) request/channel ID.
	 */
	const NULL_REQUEST_ID = 0;

	/**
	 * BEGIN-REQUEST's RESPONDER role.
	 */
	const ROLE_RESPONDER = 1;

	/**
	 * BEGIN-REQUEST's AUTHORIZER role.
	 */
	const ROLE_AUTHORIZER = 2;

	/**
	 * BEGIN-REQUEST's FILTER role.
	 */
	const ROLE_FILTER = 3;

	/**
	 * BEGIN-REQUEST's keep connection (keep alive) flag.
	 */
	const FLAG_KEEP_CONNECTION = 1;

	/**
	 * Key for VALUES frame.
	 */
	const VALUE_FCGI_MAX_CONNS = "FCGI_MAX_CONNS";

	/**
	 * Key for VALUES frame.
	 */
	const VALUE_FCGI_MAX_REQS = "FCGI_MAX_REQS";

	/**
	 * Key for VALUES frame.
	 */
	const VALUE_FCGI_MPXS_CONNS = "FCGI_MPXS_CONNS";

	/**
	 * END-REQUEST's appStatus.
	 */
	const APP_STATUS_OK = 0;

	/**
	 * END-REQUEST's appStatus.
	 */
	const APP_STATUS_ERROR = 1;

	/**
	 * END-REQUEST's protocolStatus.
	 */
	const PROTOCOL_STATUS_COMPLETE = 0;

	/**
	 * END-REQUEST's protocolStatus.
	 */
	const PROTOCOL_STATUS_CANT_MPX_CONN = 1;

	/**
	 * END-REQUEST's protocolStatus.
	 */
	const PROTOCOL_STATUS_OVERLOADED = 2;

	/**
	 * END-REQUEST's protocolStatus.
	 */
	const PROTOCOL_STATUS_UNKNOWN_ROLE = 3;

}
