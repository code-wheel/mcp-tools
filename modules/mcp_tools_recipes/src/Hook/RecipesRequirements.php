<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_recipes\Hook;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mcp_tools_recipes\Service\RecipesService;

/**
 * What MCP Tools Recipes adds to the status report.
 *
 * A recipe that an agent wrote was applied, and no person has read it
 * since. The audit log says so once. The status report says so until
 * someone has read the recipe and deleted the note in its folder.
 */
class RecipesRequirements {

  use StringTranslationTrait;

  public function __construct(
    private readonly RecipesService $recipes,
  ) {}

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtime(): array {
    return $this->unreviewed(RequirementSeverity::Warning);
  }

  /**
   * The line for the status report, if there is something to say.
   *
   * @param mixed $warning
   *   The severity "warning" as this version of Drupal names it: the enum
   *   case from 11.2, the constant REQUIREMENT_WARNING before.
   *
   * @return array
   *   Requirements, keyed by name.
   */
  public function unreviewed(mixed $warning): array {
    $names = $this->recipes->appliedUnreviewed();
    if ($names === []) {
      return [];
    }
    return [
      'mcp_tools_recipes_unreviewed' => [
        'title' => $this->t('MCP Tools: recipes nobody has reviewed'),
        'value' => $this->formatPlural(count($names), '1 recipe', '@count recipes'),
        'description' => $this->t('Applied through MCP, written through MCP, and not reviewed by a person: @names. A recipe can change any config and grant any permission. Read recipe.yml in the folder of each one, and delete the file UNREVIEWED.txt there once you have.', ['@names' => implode(', ', $names)]),
        'severity' => $warning,
      ],
    ];
  }

}
