<?php

declare(strict_types=1);

namespace OnMoon\OpenApiServerBundle\CodeGenerator\PhpParserGenerators;

use OnMoon\OpenApiServerBundle\CodeGenerator\Definitions\ClassDefinition;
use OnMoon\OpenApiServerBundle\CodeGenerator\Definitions\GeneratedFileDefinition;
use OnMoon\OpenApiServerBundle\CodeGenerator\Definitions\GraphDefinition;
use OnMoon\OpenApiServerBundle\Interfaces\RequestHandler;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use Psr\Container\ContainerInterface;

use function Safe\sprintf;

class ServiceSubscriberCodeGenerator extends CodeGenerator
{
    public function generate(GraphDefinition $graphDefinition): GeneratedFileDefinition
    {
        $subscriberDefinition = $graphDefinition->getServiceSubscriber();

        $fileBuilder = new FileBuilder($subscriberDefinition);

        $containerInterfaceClass = $fileBuilder->getReference(ClassDefinition::fromFQCN(ContainerInterface::class));
        $requestHandlerClass     = $fileBuilder->getReference(ClassDefinition::fromFQCN(RequestHandler::class));

        $classBuilder = $this
            ->factory
            ->class($fileBuilder->getReference($subscriberDefinition))
            ->setDocComment(sprintf(self::AUTOGENERATED_WARNING, 'class'));

        foreach ($subscriberDefinition->getImplements() as $implement) {
            $classBuilder->implement($fileBuilder->getReference($implement));
        }

        $services           = [];
        $responseCodeMapper = [];
        foreach ($graphDefinition->getSpecifications() as $specification) {
            foreach ($specification->getOperations() as $operation) {
                $services[]    =  new ArrayItem(
                    new Concat(
                        new String_('?'),
                        new ClassConstFetch(
                            new Name($fileBuilder->getReference($operation->getRequestHandlerInterface())),
                            'class'
                        )
                    ),
                    new String_($operation->getRequestHandlerName())
                );
                $responseTypes = [];
                if ($operation->getSingleHttpCode() !== null) {
                    $responseTypes[] = new ArrayItem(
                        new Array_([new ArrayItem(new String_($operation->getSingleHttpCode()))], ['kind' => Array_::KIND_SHORT]),
                        new String_('void')
                    );
                } else {
                    foreach ($operation->getResponses() as $response) {
                        $responseTypes[] = new ArrayItem(
                            new Array_([new ArrayItem(new String_($response->getStatusCode()))], ['kind' => Array_::KIND_SHORT]),
                            new ClassConstFetch(
                                new Name($fileBuilder->getReference($response->getResponseBody())),
                                'class'
                            )
                        );
                    }
                }

                $responseCodeMapper[] = new ArrayItem(
                    new Array_($responseTypes, ['kind' => Array_::KIND_SHORT]),
                    new ClassConstFetch(
                        new Name($fileBuilder->getReference($operation->getRequestHandlerInterface())),
                        'class'
                    )
                );
            }
        }

        $httpCodeMapper = $this
            ->factory
            ->classConst('HTTP_CODES', new Array_($responseCodeMapper, ['kind' => Array_::KIND_SHORT]))
            ->makePrivate();

        $property = $this
            ->factory
            ->property('locator')
            ->makePrivate()
            ->setType($containerInterfaceClass);
        if ($this->fullDocs) {
            $property->setDocComment('/** @var ' . $containerInterfaceClass . ' */');
        }

        $constructor = $this
            ->factory
            ->method('__construct')
            ->makePublic()
            ->addParam(
                $this->factory->param('locator')->setType($containerInterfaceClass)
            )
            ->addStmt(
                new Assign(new Variable('this->locator'), new Variable('locator'))
            );
        if ($this->fullDocs) {
            $constructor->setDocComment('/** @param ' . $containerInterfaceClass . ' $locator */');
        }

        $getSubscribedServices = $this
            ->factory
            ->method('getSubscribedServices')
            ->makePublic()
            ->makeStatic()
            ->setReturnType('array')
            ->setDocComment('/**
                                         * @inheritDoc
                                         */')
            ->addStmt(
                new Return_(
                    new Array_(
                        $services,
                        ['kind' => Array_::KIND_SHORT]
                    )
                )
            );

        $getRequestHandler = $this
            ->factory
            ->method('get')
            ->makePublic()
            ->setReturnType('?' . $requestHandlerClass)
            ->addParam(
                $this->factory->param('interface')->setType('string')
            )
            ->addStmt(
                new If_(new BooleanNot(new MethodCall(
                    new Variable('this->locator'),
                    'has',
                    [
                        new Arg(
                            new Variable('interface')
                        ),
                    ]
                )), ['stmts' => [new Return_($this->factory->val(null))]])
            )
            ->addStmt(
                new Return_(
                    new MethodCall(
                        new Variable('this->locator'),
                        'get',
                        [
                            new Arg(
                                new Variable('interface')
                            ),
                        ]
                    )
                )
            );

        if ($this->fullDocs) {
            $docs = [
                '@param string $interface',
                '@return ' . $requestHandlerClass . '|null',
            ];
            $getRequestHandler->setDocComment($this->getDocComment($docs));
        }

        $getAllowedCodes = $this->factory->method('getAllowedCodes')
            ->setReturnType('array')
            ->makePublic()
            ->setDocComment($this->getDocComment(['@return string[]']))
            ->addParams([
                $this->factory->param('apiClass')->setType('string'),
                $this->factory->param('dtoClass')->setType('string'),
            ])
            ->addStmt(
                new Return_(
                    new ArrayDimFetch(
                        new ArrayDimFetch(
                            new ClassConstFetch(new Name('self'), 'HTTP_CODES'),
                            new Variable('apiClass')
                        ),
                        new Variable('dtoClass')
                    )
                )
            );

        $classBuilder->addStmts([$property, $constructor, $httpCodeMapper, $getSubscribedServices, $getRequestHandler, $getAllowedCodes]);

        $fileBuilder->addStmt($classBuilder);

        return new GeneratedFileDefinition(
            $subscriberDefinition,
            $this->printFile($fileBuilder)
        );
    }
}
