<?php

declare(strict_types=1);

namespace Chiron\Http\ErrorHandler\Renderer;

use function file_get_contents;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

// TODO : ajouter un escape des caractéres HTML : https://github.com/symfony/error-renderer/blob/master/ErrorRenderer/HtmlErrorRenderer.php#L318

// TODO : escape des caractéres HTML => https://github.com/symfony/symfony/blob/a44f58bd79296675b93a6bfc1826d85f6bd6acca/src/Symfony/Component/ErrorHandler/ErrorRenderer/HtmlErrorRenderer.php#L183

// TODO : passer la classer en final ???
class HtmlRenderer
{
    protected $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    /**
     * Get the supported content type.
     *
     * @return string
     */
    public function contentType(): string
    {
        return 'text/html'; // TODO : mettre plutot cette valeur dans une propriété protected de la classe ca ne sert à rien d'utiliser une méthode !!!!
    }

    public function render(string $path, array $data): ResponseInterface
    {
        // TODO : lever une exception si la valeur de retour est === false car cela veut dire qu'on n'a pas réussi à lire le fichier....
        $html = file_get_contents($path, false);

        foreach ($data as $key => $val) {
            // TODO : il faudrait utiliser la fonction "h()" pour faire un html encode de la variable $val
            // TODO : gérer le cas ou la valeur est un tableau, il faudrait surement décorer les valeur dans das balises html de type "<ul><li></li></ul>"
            $html = str_replace("{{ $key }}", $val, $html);
        }

        return $this->createResponse(500, $this->contentType(), $html);
    }

    private function createResponse(int $statusCode, string $contentType, string $body): ResponseInterface
    {
        /*
                foreach (\array_merge($headers, ['Content-Type' => $this->getContentType()]) as $header => $value) {
                    $response = $response->withAddedHeader($header, $value);
                }
                $body = $response->getBody();
                $body->write(\json_encode(['errors' => [$error]], \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_AMP | \JSON_HEX_QUOT | \JSON_UNESCAPED_SLASHES));
                $body->rewind();
                return $response->withBody($body);
        */

        // TODO : attention il manque le choix de la version HTTP 1.1 ou 1.0 lorsqu'on initialise cette nouvelle response.
        $response = $this->responseFactory->createResponse($statusCode);

        // TODO : attention il manque le charset dans ce Content-Type !!!!!
        $response = $response->withHeader('Content-Type', $contentType);

        $response->getBody()->write($body);
        $response->getBody()->rewind();

        return $response;
    }

}
