<?php
/**
 * Minimal stubs for WordPress\AiClient SDK classes.
 *
 * These allow PHPUnit + Brain Monkey tests to instantiate / mock the
 * event objects without the real SDK installed.
 */

declare(strict_types=1);

namespace WordPress\AiClient\Providers\Models\Enums;

if ( ! class_exists( CapabilityEnum::class ) ) {
	class CapabilityEnum {
		private string $value;
		private function __construct( string $value ) { $this->value = $value; }
		public static function textGeneration(): self { return new self( 'text_generation' ); }
		public function __toString(): string { return $this->value; }
	}
}

namespace WordPress\AiClient\Providers\DTO;

if ( ! class_exists( ProviderMetadata::class ) ) {
	class ProviderMetadata {
		public function __construct( private readonly string $id = '', private readonly string $name = '' ) {}
		public function getId(): string { return $this->id; }
		public function getName(): string { return $this->name; }
	}
}

namespace WordPress\AiClient\Providers\Models\DTO;

if ( ! class_exists( ModelMetadata::class ) ) {
	class ModelMetadata {
		public function __construct( private readonly string $id = '', private readonly string $name = '' ) {}
		public function getId(): string { return $this->id; }
		public function getName(): string { return $this->name; }
	}
}

namespace WordPress\AiClient\Providers\Models\Contracts;

use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

if ( ! interface_exists( ModelInterface::class ) ) {
	interface ModelInterface {
		public function metadata(): ModelMetadata;
		public function providerMetadata(): ProviderMetadata;
	}
}

namespace WordPress\AiClient\Results\DTO;

if ( ! class_exists( TokenUsage::class ) ) {
	class TokenUsage {
		public function __construct(
			private readonly int $promptTokens = 0,
			private readonly int $completionTokens = 0,
			private readonly int $totalTokens = 0,
		) {}
		public function getPromptTokens(): int { return $this->promptTokens; }
		public function getCompletionTokens(): int { return $this->completionTokens; }
		public function getTotalTokens(): int { return $this->totalTokens; }
	}
}

if ( ! class_exists( GenerativeAiResult::class ) ) {
	class GenerativeAiResult {
		public function __construct( private readonly TokenUsage $tokenUsage = new TokenUsage() ) {}
		public function getTokenUsage(): TokenUsage { return $this->tokenUsage; }
	}
}

namespace WordPress\AiClient\Events;

use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;

if ( ! class_exists( BeforeGenerateResultEvent::class ) ) {
	class BeforeGenerateResultEvent {
		public function __construct(
			private readonly array $messages,
			private readonly ModelInterface $model,
			private readonly ?CapabilityEnum $capability = null,
		) {}
		public function getMessages(): array { return $this->messages; }
		public function getModel(): ModelInterface { return $this->model; }
		public function getCapability(): ?CapabilityEnum { return $this->capability; }
	}
}

if ( ! class_exists( AfterGenerateResultEvent::class ) ) {
	class AfterGenerateResultEvent {
		public function __construct(
			private readonly array $messages,
			private readonly ModelInterface $model,
			private readonly ?CapabilityEnum $capability = null,
			private readonly ?GenerativeAiResult $result = null,
		) {}
		public function getMessages(): array { return $this->messages; }
		public function getModel(): ModelInterface { return $this->model; }
		public function getCapability(): ?CapabilityEnum { return $this->capability; }
		public function getResult(): GenerativeAiResult { return $this->result ?? new GenerativeAiResult(); }
	}
}
