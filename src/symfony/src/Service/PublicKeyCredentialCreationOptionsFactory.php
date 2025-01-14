<?php

declare(strict_types=1);

namespace Webauthn\Bundle\Service;

use function array_key_exists;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Webauthn\AuthenticationExtensions\AuthenticationExtension;
use Webauthn\AuthenticationExtensions\AuthenticationExtensionsClientInputs;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\Bundle\Event\PublicKeyCredentialCreationOptionsCreatedEvent;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

final class PublicKeyCredentialCreationOptionsFactory
{
    /**
     * @param mixed[] $profiles
     */
    public function __construct(
        private readonly array $profiles,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @param PublicKeyCredentialDescriptor[] $excludeCredentials
     */
    public function create(
        string $key,
        PublicKeyCredentialUserEntity $userEntity,
        array $excludeCredentials = [],
        ?AuthenticatorSelectionCriteria $authenticatorSelection = null,
        ?string $attestationConveyance = null,
        ?AuthenticationExtensionsClientInputs $authenticationExtensionsClientInputs = null
    ): PublicKeyCredentialCreationOptions {
        array_key_exists($key, $this->profiles) || throw new RuntimeException(sprintf(
            'The profile with key "%s" does not exist.',
            $key
        ));
        $profile = $this->profiles[$key];

        $options = PublicKeyCredentialCreationOptions
            ::create(
                $this->createRpEntity($profile),
                $userEntity,
                random_bytes($profile['challenge_length']),
                $this->createCredentialParameters($profile)
            )
                ->excludeCredentials(...$excludeCredentials)
                ->setAuthenticatorSelection(
                    $authenticatorSelection ?? $this->createAuthenticatorSelectionCriteria($profile)
                )
                ->setAttestation($attestationConveyance ?? $profile['attestation_conveyance'])
                ->setExtensions($authenticationExtensionsClientInputs ?? $this->createExtensions($profile))
                ->setTimeout($profile['timeout']);
        $this->eventDispatcher->dispatch(new PublicKeyCredentialCreationOptionsCreatedEvent($options));

        return $options;
    }

    /**
     * @param mixed[] $profile
     */
    private function createExtensions(array $profile): AuthenticationExtensionsClientInputs
    {
        $extensions = new AuthenticationExtensionsClientInputs();
        foreach ($profile['extensions'] as $k => $v) {
            $extensions->add(AuthenticationExtension::create($k, $v));
        }

        return $extensions;
    }

    /**
     * @param mixed[] $profile
     */
    private function createAuthenticatorSelectionCriteria(array $profile): AuthenticatorSelectionCriteria
    {
        return AuthenticatorSelectionCriteria::create()
            ->setAuthenticatorAttachment($profile['authenticator_selection_criteria']['attachment_mode'])
            ->setUserVerification($profile['authenticator_selection_criteria']['user_verification'])
            ->setRequireResidentKey($profile['authenticator_selection_criteria']['require_resident_key'])
            ->setResidentKey($profile['authenticator_selection_criteria']['resident_key']);
    }

    /**
     * @param mixed[] $profile
     */
    private function createRpEntity(array $profile): PublicKeyCredentialRpEntity
    {
        return new PublicKeyCredentialRpEntity($profile['rp']['name'], $profile['rp']['id'], $profile['rp']['icon']);
    }

    /**
     * @param mixed[] $profile
     *
     * @return PublicKeyCredentialParameters[]
     */
    private function createCredentialParameters(array $profile): array
    {
        $callback = static fn ($alg): PublicKeyCredentialParameters => new PublicKeyCredentialParameters(
            PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            $alg
        );

        return array_map($callback, $profile['public_key_credential_parameters']);
    }
}
