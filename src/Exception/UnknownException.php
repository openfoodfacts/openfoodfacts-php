<?php

namespace OpenFoodFacts\Exception;

/**
 * Extends BadRequestException so that pre-existing "catch (BadRequestException)"
 * blocks written against older SDK versions keep catching unexpected API responses.
 */
class UnknownException extends BadRequestException
{
}
