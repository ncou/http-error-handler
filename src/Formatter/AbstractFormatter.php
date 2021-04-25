<?php

declare(strict_types=1);

namespace Chiron\Http\ErrorHandler\Formatter;

use Chiron\Http\Exception\HttpException;
use Throwable;

abstract class AbstractFormatter implements FormatterInterface
{
    /** @var int */
    protected $defaultErrorStatusCode = 500;

    /** @var string */
    protected $defaultErrorTitle = 'Chiron Application Error'; // TODO : récupérer plutot le nom de l'application !!!!

    /** @var string */
    //protected $defaultErrorDetail = 'A website error has occurred. Sorry for the temporary inconvenience.';
    protected $defaultErrorDetail = 'Whoops, looks like something went wrong.'; //'Hm... Unfortunately, the server crashed. Apologies.'

    /*
This is awkward.
We encountered a 500 error.

--------

Apologies. Something broke.

Unfortunately, there was an error.

--------

Things are a little unstable here.
I suggest come back later.

-----

500 - Our server is on a break


Whoops! something went wrong

Unexpected error

Sorry, something went wrong on our end.

Uh ho, there seems to be a problem.

Unfortunately, something has gone wrong.

Well, this is embarassing...
We are sorry this isn't working properly.

Shoot!
Well, this is unexpected...


A server error occurred. Please contact the administrator.

Hm... Unfortunately, the server crashed. Apologies.

A website error has occurred. Sorry for the temporary inconvenience.

    */

    protected function getErrorTitle(Throwable $exception): string
    {
        if ($exception instanceof HttpException) {
            return $exception->getTitle();
        }

        return $this->defaultErrorTitle;
    }

    protected function getErrorDetail(Throwable $exception): string
    {
        if ($exception instanceof HttpException) {
            return $exception->getDetail();
        }

        return $this->defaultErrorDetail;
    }

    protected function getErrorStatusCode(Throwable $exception): int
    {
        if ($exception instanceof HttpException) {
            return $exception->getStatusCode();
        }

        return $this->defaultErrorStatusCode;
    }
}
