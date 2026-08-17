<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Form\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class AdminNewUser
{
    #[Assert\Length(min: 3, max: 32, normalizer: 'trim')]
    #[Assert\Regex('/^[A-Za-z0-9_-]+$/', message: 'The username may only contain letters, numbers, hyphens, and underscores.')]
    #[Assert\Regex('/[A-Za-z]/', message: 'The username must contain at least one letter.')]
    private string $username = '';

    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 8)]
    private string $password = '';

    private bool $administrator = false;

    public function getUsername(): string { return $this->username; }
    public function setUsername(string $username): void { $this->username = trim($username); }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): void { $this->email = trim($email); }
    public function getPassword(): string { return $this->password; }
    public function setPassword(#[\SensitiveParameter] string $password): void { $this->password = $password; }
    public function isAdministrator(): bool { return $this->administrator; }
    public function setAdministrator(bool $administrator): void { $this->administrator = $administrator; }
}
