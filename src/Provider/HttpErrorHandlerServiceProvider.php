<?php

declare(strict_types=1);



//https://github.com/userfrosting/UserFrosting/blob/master/app/system/ServicesProvider.php
//https://github.com/slimphp/Slim/blob/3.x/Slim/DefaultServicesProvider.php

//https://github.com/laracasts/Favorite-This-Demo/blob/master/vendor/filp/whoops/src/Whoops/Provider/Silex/WhoopsServiceProvider.php

//https://github.com/rougin/slytherin/blob/b712b88af8f3dcd24c2814f7b28c9abb5a5919c3/src/Debug/ErrorHandlerIntegration.php

//https://github.com/narrowspark/framework/blob/ccda2dca0c312dbea08814d1372c1802920ebcca/src/Viserio/Component/Exception/Provider/HttpExceptionServiceProvider.php

namespace Chiron\Http\ErrorHandler\Provider;

use Chiron\Core\Environment;
use Chiron\Core\Container\Provider\ServiceProviderInterface;
use Chiron\Container\BindingInterface;
use Chiron\Container\Container;
use Chiron\Http\ErrorHandler\HttpErrorHandler;
use Chiron\Http\ErrorHandler\ErrorManager;
use Chiron\Http\ErrorHandler\Formatter\HtmlFormatter;
use Chiron\Http\ErrorHandler\Formatter\JsonFormatter;
use Chiron\Http\ErrorHandler\Formatter\PlainTextFormatter;
use Chiron\Http\ErrorHandler\Formatter\ViewFormatter;
use Chiron\Http\ErrorHandler\Formatter\WhoopsFormatter;
use Chiron\Http\ErrorHandler\Formatter\XmlFormatter;
use Chiron\Http\ErrorHandler\Reporter\LoggerReporter;
use Chiron\Http\Exception\Client\NotFoundHttpException;
use Chiron\Http\Exception\Server\ServiceUnavailableHttpException;
use Chiron\Views\TemplateRendererInterface;
use Closure;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Chiron\Core\Core;

/**
 * Chiron error handler services provider.
 */
// TODO : éventuellement déplacer cette classe de serviceProvider directement dans le package chiron/http car il y a une dépendance vers ce package de http vers http-errorhandler donc ca sera plus propre !!!! Argument de plus si on laisse cette classe dans le package chiron/http-error-handler il faudra ajouter une dépendance vers le module chiron/core car on a besoin du container pour classe du serviceprovider !!!!

// TODO : créer un bootloader pour permettre de publish le fichier ressources/error.html comme un asset pour le configurer la page d'erreur dans l'application de l'utilisateur + ajouter la possibilité de charger cette ressource soit depuis le répertoire de l'application si cette ressource existe, sinon en fallback on récupére le template (error.html) présent dans ce package.

final class HttpErrorHandlerServiceProvider implements ServiceProviderInterface
{
    /**
     * Register Chiron system services.
     *
     * @param ContainerInterface $container A DI container implementing ArrayAccess and container-interop.
     */
    public function register(BindingInterface $container): void
    {
        // TODO : améliorer le cas du html avec une erreur 404, le lien javascript pour revenir à la page d'accueil ne fonctionne pas bien si on a un basePath différent de "/"
        $container->bind(HtmlFormatter::class, function () {
            $path = __DIR__ . '/../../resources/error.html';

            return new HtmlFormatter(realpath($path));
        });

        // TODO : il faudrait plutot binder l'interface ErrorHandlerInterface et pas directement la classe ErrorHandler, cela permettra à l'utilisateur de faire un override du module de gestion des erreurs/exceptions.
        $container->bind(HttpErrorHandler::class, function (ContainerInterface $container) {
            // TODO : aller chercher la responsefactory directement dans le container plutot que de faire un new ResponseFactory !!!!
            $errorHandler = new HttpErrorHandler($container->get('responseFactory'));

            //$errorHandler->addReporter($container->get(LoggerReporter::class));

            $errorHandler->addFormatter(new WhoopsFormatter());

            $hasRenderer = $container->has(TemplateRendererInterface::class);
            // TODO : en plus du has il faut vérifier si il est bien de l'instance TamplateRendererInterface pour rentrer dans le if !!!!
            if ($hasRenderer) {
                $renderer = $container->get(TemplateRendererInterface::class);
                //registerErrorViewPaths($renderer);
                //$renderer->addPath(\Chiron\TEMPLATES_DIR . "/errors", 'errors');
                $errorHandler->addFormatter(new ViewFormatter($renderer));
            }

            $errorHandler->addFormatter($container->get(HtmlFormatter::class));
            $errorHandler->addFormatter(new JsonFormatter());
            $errorHandler->addFormatter(new XmlFormatter());
            $errorHandler->addFormatter(new PlainTextFormatter());

            //$errorHandler->setDefaultFormatter($c[HtmlFormatter::class]);
            $errorHandler->setDefaultFormatter(new PlainTextFormatter());

            return $errorHandler;
        });

        // TODO : à virer c'est un test
        /*
                $container->bind(ErrorHandler::class, function ($container) {
                    $errorHandler = new ErrorHandler($container->get('responseFactory'));

                    $errorHandler->setDefaultFormatter(new PlainTextFormatter());

                    return $errorHandler;
                });*/

        /*
         * Register all the possible error template namespaced paths.
         */
        // TODO : virer cette fonction et améliorer l'intialisation du répertoire des erreurs pour les templates
        //https://github.com/laravel/framework/blob/master/src/Illuminate/Foundation/Exceptions/Handler.php#L391
        //https://laravel-news.com/laravel-5-5-error-views
        /*
        function registerErrorViewPaths(TemplateRendererInterface $renderer)
        {
            $paths = $renderer->getPaths();

            // add all possible folders for errors based on the presents paths
            foreach ($paths as $path) {
                $renderer->addPath($path . '/errors', 'errors');
            }
            // at the end of the stack and in last resort we add the framework error template folder
            $renderer->addPath(__DIR__ . '/../../resources/errors', 'errors');
        }
        */

        $container->singleton(ErrorManager::class, Closure::fromCallable([$this, 'errorManager']));
    }

    // TODO : éventuellement séparer cette méthode en deux parties, une pour enregistrer la classe et la seconde pour configurer la partie "bindHandler" ce sera plus propre.
    // TODO : attention le logger n'est pas utilisé dans le code de la méthode "errorManager()" il faudra aussi vérifier que le LoggerInterface est correctement initialisé car si on ajoute un bridge pour Monolog il faudra s'assurer que celui ci est bien initialisé et qu'il n'y aura pas d'erreurs lors de la récupération du LoggerInterface !!!!
    private function errorManager(Core $core, HttpErrorHandler $handler, LoggerInterface $logger): ErrorManager
    {
        //$manager = new ErrorManager($container->get('config')->app['debug']);
        //$manager = new ErrorManager($container->get('config')->get('app.debug'));

        //$manager = new ErrorManager(true);

        // TODO : améliorer le code, soit passer la classe SettingsConfig au constructeur de la classe ErrorManager, soit éclater ce code en deux partie avec un provider et un bootloader qui se chargera de faire le bindHandler et le setLogger. Voir même ne plus faire le setLogger si on utilise la mutation LoggerAware qui se chargera d'injecter automatiquement le logger par défaut !!! Ou alors ajouter la classe SingletonInterface au ErrorManager + créer une méthode setDebug() qui serait appellée par un bootloader, comme on fait pour Environement ou pour Directory.


        $debug = $core->isDebug();
        //$debug = true;

        $manager = new ErrorManager($debug);

        //$manager->bindHandler(Throwable::class, new \Chiron\Exception\WhoopsHandler());

        //$manager->bindHandler(Throwable::class, $container->get(ErrorHandler::class));
        $manager->bindHandler(Throwable::class, $handler);

        //$manager->bindHandler(ServiceUnavailableHttpException::class, new \Chiron\Exception\MaintenanceHandler());
        //$manager->bindHandler(NotFoundHttpException::class, new \Chiron\Exception\NotFoundHandler());

        $manager->setLogger($logger);

        return $manager;
    }
}
