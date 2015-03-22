<?php
namespace React\Fcgi\Exception;

/**
 * Handler returned non-{@link Symfony\Component\HttpFoundation\Response} result.
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class BadResponseException extends ConnectionException
{
}
