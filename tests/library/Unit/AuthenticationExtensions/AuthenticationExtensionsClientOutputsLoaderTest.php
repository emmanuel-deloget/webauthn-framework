<?php

declare(strict_types=1);

namespace Webauthn\Tests\Unit\AuthenticationExtensions;

use CBOR\ByteStringObject;
use CBOR\MapItem;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\OtherObject\TrueObject;
use const JSON_THROW_ON_ERROR;
use PHPUnit\Framework\TestCase;
use Webauthn\AuthenticationExtensions\AuthenticationExtensionsClientOutputs;
use Webauthn\AuthenticationExtensions\AuthenticationExtensionsClientOutputsLoader;
use Webauthn\Exception\AuthenticationExtensionException;

/**
 * @internal
 */
final class AuthenticationExtensionsClientOutputsLoaderTest extends TestCase
{
    /**
     * @test
     */
    public function theExtensionsCanBeLoaded(): void
    {
        $cbor = new MapObject([new MapItem(new ByteStringObject('loc'), new TrueObject())]);

        $extensions = AuthenticationExtensionsClientOutputsLoader::load($cbor);

        static::assertInstanceOf(AuthenticationExtensionsClientOutputs::class, $extensions);
        static::assertCount(1, $extensions);
        static::assertSame('{"loc":true}', json_encode($extensions, JSON_THROW_ON_ERROR));
    }

    /**
     * @test
     */
    public function theCBORObjectIsInvalid(): void
    {
        $this->expectException(AuthenticationExtensionException::class);
        $this->expectExceptionMessage('Invalid extension object');
        $cbor = new ByteStringObject('loc');

        AuthenticationExtensionsClientOutputsLoader::load($cbor);
    }

    /**
     * @test
     */
    public function theMapKeyIsNotAString(): void
    {
        $this->expectException(AuthenticationExtensionException::class);
        $this->expectExceptionMessage('Invalid extension key');
        $cbor = new MapObject([new MapItem(NegativeIntegerObject::create(-100), new TrueObject())]);

        AuthenticationExtensionsClientOutputsLoader::load($cbor);
    }
}
