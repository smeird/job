<?php

declare(strict_types=1);

namespace App\Contacts;

use RuntimeException;

use function filter_var;
use function preg_match;
use function str_replace;
use function trim;
use function mb_strlen;

use const FILTER_VALIDATE_EMAIL;

/**
 * ContactDetailsService coordinates validation and persistence of the
 * applicant's reusable address and contact channels.
 */
final class ContactDetailsService
{
    /** @var ContactDetailsRepository */
    private $repository;

    /**
     * Inject the repository dependency used for database persistence.
     */
    public function __construct(ContactDetailsRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Retrieve the stored contact details for the supplied user identifier.
     *
     * Exposing the data through the service allows controllers to remain
     * agnostic about the underlying repository implementation.
     *
     * @return array<string, mixed>|null
     */
    public function getContactDetails(int $userId): ?array
    {
        return $this->repository->findForUser($userId);
    }

    /**
     * Validate the submitted address, phone, and email values.
     *
     * Returning a list of human-readable error strings keeps presentation logic
     * straightforward within calling controllers.
     *
     * @return array<int, string>
     */
    public function validate(string $address, string $phone, string $email): array
    {
        $errors = [];
        $addressValue = trim($address);

        if ($addressValue === '') {
            $errors[] = 'Enter your full home address.';
        } elseif (mb_strlen($addressValue) < 10) {
            $errors[] = 'Your home address must be at least 10 characters long.';
        }

        $phoneValue = trim($phone);

        if ($phoneValue !== '' && preg_match('/^[0-9+()\s-]{6,}$/', $phoneValue) !== 1) {
            $errors[] = 'Enter a valid phone number or leave the field blank.';
        }

        $emailValue = trim($email);

        if ($emailValue !== '' && filter_var($emailValue, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Enter a valid contact email address or leave the field blank.';
        }

        return $errors;
    }

    /**
     * Persist the normalised contact details for the user.
     *
     * Normalisation ensures newlines are consistent and optional fields are
     * stored as NULL when omitted.
     *
     * @return array<string, mixed>
     */
    public function saveContactDetails(int $userId, string $address, ?string $phone, ?string $email): array
    {
        $normalisedAddress = str_replace(["\r\n", "\r"], "\n", trim($address));
        $normalisedPhone = $phone !== null && trim($phone) !== '' ? trim($phone) : null;
        $normalisedEmail = $email !== null && trim($email) !== '' ? trim($email) : null;

        if ($normalisedAddress === '') {
            throw new RuntimeException('Home address is required before saving contact details.');
        }

        return $this->repository->upsert($userId, $normalisedAddress, $normalisedPhone, $normalisedEmail);
    }
}
