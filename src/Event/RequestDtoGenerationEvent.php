<?php

declare(strict_types=1);

namespace OnMoon\OpenApiServerBundle\Event;

use OnMoon\OpenApiServerBundle\CodeGenerator\Dto\Definitions\RequestDtoDefinition;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * The RequestDtoGenerationEvent event occurs before the request
 * dto is generated.
 *
 * This event allows you to modify the definitions of the generated
 * request DTO customizing the generated code.
 */
class RequestDtoGenerationEvent extends Event
{
    private RequestDtoDefinition $definition;

    public function __construct(RequestDtoDefinition $definition)
    {
        $this->definition = $definition;
    }

    public function definition() : RequestDtoDefinition
    {
        return $this->definition;
    }
}
