<?php

declare(strict_types=1);

namespace Drupal\mcp_tools_templates\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mcp_tools\Service\AccessManager;
use Drupal\mcp_tools\Service\AuditLogger;

/**
 * Service for site configuration templates.
 *
 * Provides tools for listing, previewing, applying, and exporting site
 * configuration templates for common use cases like blogs, portfolios,
 * business sites, and documentation.
 */
class TemplateService {

  /**
   * Built-in template definitions.
   */
  protected const TEMPLATES = [
    'blog' => [
      'id' => 'blog',
      'label' => 'Blog',
      'description' => 'A complete blog setup with articles, categories, tags, and author workflow.',
      'category' => 'Content',
      'components' => [
        'content_types' => [
          'article' => [
            'label' => 'Article',
            'description' => 'Blog articles with body, image, tags, and categories.',
            'fields' => [
              'body' => ['type' => 'text_with_summary', 'label' => 'Body'],
              'field_image' => ['type' => 'image', 'label' => 'Featured Image'],
              'field_tags' => ['type' => 'entity_reference', 'label' => 'Tags', 'target' => 'taxonomy_term:tags'],
              'field_categories' => ['type' => 'entity_reference', 'label' => 'Categories', 'target' => 'taxonomy_term:categories'],
            ],
          ],
        ],
        'vocabularies' => [
          'tags' => [
            'label' => 'Tags',
            'description' => 'Free-form tags for articles.',
          ],
          'categories' => [
            'label' => 'Categories',
            'description' => 'Hierarchical categories for organizing articles.',
            'hierarchy' => TRUE,
          ],
        ],
        'roles' => [
          'author' => [
            'label' => 'Author',
            'permissions' => [
              'create article content',
              'edit own article content',
              'delete own article content',
              'use text format basic_html',
            ],
          ],
        ],
        'views' => [
          'recent_articles' => [
            'label' => 'Recent Articles',
            'description' => 'A listing of recent blog articles.',
            'base_table' => 'node_field_data',
            'display' => ['page' => '/articles', 'block' => TRUE],
            'filters' => ['type' => 'article', 'status' => 1],
            'sort' => ['created' => 'DESC'],
            'pager' => 10,
          ],
        ],
      ],
    ],
    'portfolio' => [
      'id' => 'portfolio',
      'label' => 'Portfolio',
      'description' => 'Showcase projects with skills taxonomy and gallery support.',
      'category' => 'Content',
      'components' => [
        'content_types' => [
          'project' => [
            'label' => 'Project',
            'description' => 'Portfolio project with description, images, and skills.',
            'fields' => [
              'body' => ['type' => 'text_with_summary', 'label' => 'Description'],
              'field_project_images' => ['type' => 'image', 'label' => 'Project Images', 'cardinality' => -1],
              'field_skills' => ['type' => 'entity_reference', 'label' => 'Skills', 'target' => 'taxonomy_term:skills', 'cardinality' => -1],
              'field_project_url' => ['type' => 'link', 'label' => 'Project URL'],
              'field_client' => ['type' => 'string', 'label' => 'Client'],
              'field_completion_date' => ['type' => 'datetime', 'label' => 'Completion Date'],
            ],
          ],
        ],
        'vocabularies' => [
          'skills' => [
            'label' => 'Skills',
            'description' => 'Skills and technologies used in projects.',
          ],
        ],
        'media_types' => [
          'gallery' => [
            'label' => 'Gallery',
            'description' => 'Image gallery for project showcases.',
            'source' => 'image',
          ],
        ],
        'views' => [
          'portfolio_grid' => [
            'label' => 'Portfolio Grid',
            'description' => 'Grid display of portfolio projects.',
            'base_table' => 'node_field_data',
            'display' => ['page' => '/portfolio'],
            'filters' => ['type' => 'project', 'status' => 1],
            'sort' => ['created' => 'DESC'],
            'style' => 'grid',
          ],
        ],
      ],
    ],
    'business' => [
      'id' => 'business',
      'label' => 'Business',
      'description' => 'Business site with pages, services, team members, and contact form.',
      'category' => 'Corporate',
      'components' => [
        'content_types' => [
          'page' => [
            'label' => 'Page',
            'description' => 'Basic pages for static content.',
            'fields' => [
              'body' => ['type' => 'text_with_summary', 'label' => 'Body'],
              'field_banner_image' => ['type' => 'image', 'label' => 'Banner Image'],
            ],
          ],
          'service' => [
            'label' => 'Service',
            'description' => 'Business services offered.',
            'fields' => [
              'body' => ['type' => 'text_with_summary', 'label' => 'Description'],
              'field_service_icon' => ['type' => 'string', 'label' => 'Icon Class'],
              'field_service_features' => ['type' => 'string', 'label' => 'Features', 'cardinality' => -1],
              'field_price' => ['type' => 'string', 'label' => 'Price'],
            ],
          ],
          'team_member' => [
            'label' => 'Team Member',
            'description' => 'Staff and team member profiles.',
            'fields' => [
              'body' => ['type' => 'text_with_summary', 'label' => 'Bio'],
              'field_photo' => ['type' => 'image', 'label' => 'Photo'],
              'field_position' => ['type' => 'string', 'label' => 'Position'],
              'field_email' => ['type' => 'email', 'label' => 'Email'],
              'field_social_links' => ['type' => 'link', 'label' => 'Social Links', 'cardinality' => -1],
            ],
          ],
        ],
        'webforms' => [
          'contact' => [
            'label' => 'Contact',
            'description' => 'Contact form for inquiries.',
            'elements' => [
              'name' => ['type' => 'textfield', 'title' => 'Name', 'required' => TRUE],
              'email' => ['type' => 'email', 'title' => 'Email', 'required' => TRUE],
              'phone' => ['type' => 'tel', 'title' => 'Phone'],
              'message' => ['type' => 'textarea', 'title' => 'Message', 'required' => TRUE],
            ],
          ],
        ],
        'views' => [
          'services_list' => [
            'label' => 'Services',
            'description' => 'List of business services.',
            'base_table' => 'node_field_data',
            'display' => ['page' => '/services', 'block' => TRUE],
            'filters' => ['type' => 'service', 'status' => 1],
            'sort' => ['title' => 'ASC'],
          ],
          'team' => [
            'label' => 'Our Team',
            'description' => 'Grid of team members.',
            'base_table' => 'node_field_data',
            'display' => ['page' => '/team', 'block' => TRUE],
            'filters' => ['type' => 'team_member', 'status' => 1],
            'sort' => ['title' => 'ASC'],
            'style' => 'grid',
          ],
        ],
      ],
    ],
    'documentation' => [
      'id' => 'documentation',
      'label' => 'Documentation',
      'description' => 'Technical documentation with hierarchical structure and API references.',
      'category' => 'Technical',
      'components' => [
        'content_types' => [
          'doc' => [
            'label' => 'Documentation',
            'description' => 'Documentation pages with code examples.',
            'fields' => [
              'body' => ['type' => 'text_with_summary', 'label' => 'Content'],
              'field_toc' => ['type' => 'entity_reference', 'label' => 'Table of Contents Section', 'target' => 'taxonomy_term:toc'],
              'field_doc_version' => ['type' => 'string', 'label' => 'Version'],
              'field_code_examples' => ['type' => 'text_long', 'label' => 'Code Examples', 'cardinality' => -1],
              'field_related_docs' => ['type' => 'entity_reference', 'label' => 'Related Documentation', 'target' => 'node:doc', 'cardinality' => -1],
            ],
          ],
          'api_reference' => [
            'label' => 'API Reference',
            'description' => 'API endpoint documentation.',
            'fields' => [
              'body' => ['type' => 'text_with_summary', 'label' => 'Description'],
              'field_endpoint' => ['type' => 'string', 'label' => 'Endpoint'],
              'field_method' => ['type' => 'list_string', 'label' => 'HTTP Method', 'allowed_values' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']],
              'field_parameters' => ['type' => 'text_long', 'label' => 'Parameters'],
              'field_response' => ['type' => 'text_long', 'label' => 'Response Example'],
              'field_toc' => ['type' => 'entity_reference', 'label' => 'API Section', 'target' => 'taxonomy_term:toc'],
            ],
          ],
        ],
        'vocabularies' => [
          'toc' => [
            'label' => 'Table of Contents',
            'description' => 'Hierarchical documentation structure.',
            'hierarchy' => TRUE,
          ],
        ],
        'views' => [
          'documentation_toc' => [
            'label' => 'Documentation TOC',
            'description' => 'Hierarchical table of contents for documentation.',
            'base_table' => 'taxonomy_term_field_data',
            'display' => ['block' => TRUE],
            'filters' => ['vid' => 'toc'],
            'sort' => ['weight' => 'ASC', 'name' => 'ASC'],
            'style' => 'tree',
          ],
          'api_reference_list' => [
            'label' => 'API Reference',
            'description' => 'List of API endpoints.',
            'base_table' => 'node_field_data',
            'display' => ['page' => '/api'],
            'filters' => ['type' => 'api_reference', 'status' => 1],
            'sort' => ['field_endpoint' => 'ASC'],
          ],
        ],
      ],
    ],
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected AccessManager $accessManager,
    protected AuditLogger $auditLogger,
  ) {}

  /**
   * List available built-in templates.
   *
   * @return array
   *   Result array with list of templates.
   */
  public function listTemplates(): array {
    $templates = [];

    foreach (self::TEMPLATES as $id => $template) {
      $templates[] = [
        'id' => $id,
        'label' => $template['label'],
        'description' => $template['description'],
        'category' => $template['category'],
        'component_summary' => $this->getComponentSummary($template['components']),
      ];
    }

    return [
      'success' => TRUE,
      'data' => [
        'templates' => $templates,
        'count' => count($templates),
      ],
    ];
  }

  /**
   * Get detailed information about a specific template.
   *
   * @param string $id
   *   The template ID.
   *
   * @return array
   *   Result array with template details.
   */
  public function getTemplate(string $id): array {
    if (!isset(self::TEMPLATES[$id])) {
      return [
        'success' => FALSE,
        'error' => sprintf('Template "%s" not found.', $id),
        'code' => 'TEMPLATE_NOT_FOUND',
        'available_templates' => array_keys(self::TEMPLATES),
      ];
    }

    $template = self::TEMPLATES[$id];

    return [
      'success' => TRUE,
      'data' => [
        'id' => $id,
        'label' => $template['label'],
        'description' => $template['description'],
        'category' => $template['category'],
        'components' => $template['components'],
        'component_summary' => $this->getComponentSummary($template['components']),
      ],
    ];
  }

  /**
   * Preview what would be created by applying a template (dry-run).
   *
   * @param string $id
   *   The template ID.
   *
   * @return array
   *   Result array with preview of changes.
   */
  public function previewTemplate(string $id): array {
    if (!isset(self::TEMPLATES[$id])) {
      return [
        'success' => FALSE,
        'error' => sprintf('Template "%s" not found.', $id),
        'code' => 'TEMPLATE_NOT_FOUND',
      ];
    }

    $template = self::TEMPLATES[$id];
    $preview = [
      'template_id' => $id,
      'template_label' => $template['label'],
      'will_create' => [],
      'will_skip' => [],
      'conflicts' => [],
    ];

    $components = $template['components'];

    // Check content types.
    if (!empty($components['content_types'])) {
      $nodeTypeStorage = $this->entityTypeManager->getStorage('node_type');
      foreach ($components['content_types'] as $typeId => $typeConfig) {
        $existing = $nodeTypeStorage->load($typeId);
        if ($existing) {
          $preview['will_skip'][] = [
            'type' => 'content_type',
            'id' => $typeId,
            'label' => $typeConfig['label'],
            'reason' => 'Already exists',
          ];
        }
        else {
          $preview['will_create'][] = [
            'type' => 'content_type',
            'id' => $typeId,
            'label' => $typeConfig['label'],
            'fields' => array_keys($typeConfig['fields'] ?? []),
          ];
        }
      }
    }

    // Check vocabularies.
    if (!empty($components['vocabularies'])) {
      $vocabStorage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
      foreach ($components['vocabularies'] as $vocabId => $vocabConfig) {
        $existing = $vocabStorage->load($vocabId);
        if ($existing) {
          $preview['will_skip'][] = [
            'type' => 'vocabulary',
            'id' => $vocabId,
            'label' => $vocabConfig['label'],
            'reason' => 'Already exists',
          ];
        }
        else {
          $preview['will_create'][] = [
            'type' => 'vocabulary',
            'id' => $vocabId,
            'label' => $vocabConfig['label'],
          ];
        }
      }
    }

    // Check roles.
    if (!empty($components['roles'])) {
      $roleStorage = $this->entityTypeManager->getStorage('user_role');
      foreach ($components['roles'] as $roleId => $roleConfig) {
        $existing = $roleStorage->load($roleId);
        if ($existing) {
          $preview['will_skip'][] = [
            'type' => 'role',
            'id' => $roleId,
            'label' => $roleConfig['label'],
            'reason' => 'Already exists',
          ];
        }
        else {
          $preview['will_create'][] = [
            'type' => 'role',
            'id' => $roleId,
            'label' => $roleConfig['label'],
            'permissions' => $roleConfig['permissions'] ?? [],
          ];
        }
      }
    }

    // Check views.
    if (!empty($components['views'])) {
      $viewStorage = $this->entityTypeManager->getStorage('view');
      foreach ($components['views'] as $viewId => $viewConfig) {
        $existing = $viewStorage->load($viewId);
        if ($existing) {
          $preview['will_skip'][] = [
            'type' => 'view',
            'id' => $viewId,
            'label' => $viewConfig['label'],
            'reason' => 'Already exists',
          ];
        }
        else {
          $preview['will_create'][] = [
            'type' => 'view',
            'id' => $viewId,
            'label' => $viewConfig['label'],
          ];
        }
      }
    }

    // Check media types.
    if (!empty($components['media_types'])) {
      try {
        $mediaTypeStorage = $this->entityTypeManager->getStorage('media_type');
        foreach ($components['media_types'] as $mediaTypeId => $mediaTypeConfig) {
          $existing = $mediaTypeStorage->load($mediaTypeId);
          if ($existing) {
            $preview['will_skip'][] = [
              'type' => 'media_type',
              'id' => $mediaTypeId,
              'label' => $mediaTypeConfig['label'],
              'reason' => 'Already exists',
            ];
          }
          else {
            $preview['will_create'][] = [
              'type' => 'media_type',
              'id' => $mediaTypeId,
              'label' => $mediaTypeConfig['label'],
            ];
          }
        }
      }
      catch (\Exception $e) {
        $preview['conflicts'][] = [
          'type' => 'media_type',
          'reason' => 'Media module not installed',
        ];
      }
    }

    // Check webforms.
    if (!empty($components['webforms'])) {
      try {
        $webformStorage = $this->entityTypeManager->getStorage('webform');
        foreach ($components['webforms'] as $webformId => $webformConfig) {
          $existing = $webformStorage->load($webformId);
          if ($existing) {
            $preview['will_skip'][] = [
              'type' => 'webform',
              'id' => $webformId,
              'label' => $webformConfig['label'],
              'reason' => 'Already exists',
            ];
          }
          else {
            $preview['will_create'][] = [
              'type' => 'webform',
              'id' => $webformId,
              'label' => $webformConfig['label'],
            ];
          }
        }
      }
      catch (\Exception $e) {
        $preview['conflicts'][] = [
          'type' => 'webform',
          'reason' => 'Webform module not installed',
        ];
      }
    }

    return [
      'success' => TRUE,
      'data' => $preview,
    ];
  }

  /**
   * Apply a template to the site.
   *
   * @param string $id
   *   The template ID.
   * @param array $options
   *   Optional configuration options:
   *   - skip_existing: Skip components that already exist (default: TRUE)
   *   - components: Array of component types to apply (default: all)
   *
   * @return array
   *   Result array indicating success or failure.
   */
  public function applyTemplate(string $id, array $options = []): array {
    // Require admin scope for applying templates.
    if (!$this->accessManager->canAdmin()) {
      return [
        'success' => FALSE,
        'error' => 'Applying templates requires admin scope. This operation can make significant changes to your site.',
        'code' => 'INSUFFICIENT_SCOPE',
      ];
    }

    if (!isset(self::TEMPLATES[$id])) {
      return [
        'success' => FALSE,
        'error' => sprintf('Template "%s" not found.', $id),
        'code' => 'TEMPLATE_NOT_FOUND',
      ];
    }

    $template = self::TEMPLATES[$id];
    $components = $template['components'];
    $skipExisting = $options['skip_existing'] ?? TRUE;
    $componentFilter = $options['components'] ?? NULL;

    $created = [];
    $skipped = [];
    $errors = [];

    // Create vocabularies first (needed for reference fields).
    if (!empty($components['vocabularies']) && ($componentFilter === NULL || in_array('vocabularies', $componentFilter))) {
      $result = $this->createVocabularies($components['vocabularies'], $skipExisting);
      $created = array_merge($created, $result['created']);
      $skipped = array_merge($skipped, $result['skipped']);
      $errors = array_merge($errors, $result['errors']);
    }

    // Create roles.
    if (!empty($components['roles']) && ($componentFilter === NULL || in_array('roles', $componentFilter))) {
      $result = $this->createRoles($components['roles'], $skipExisting);
      $created = array_merge($created, $result['created']);
      $skipped = array_merge($skipped, $result['skipped']);
      $errors = array_merge($errors, $result['errors']);
    }

    // Create content types.
    if (!empty($components['content_types']) && ($componentFilter === NULL || in_array('content_types', $componentFilter))) {
      $result = $this->createContentTypes($components['content_types'], $skipExisting);
      $created = array_merge($created, $result['created']);
      $skipped = array_merge($skipped, $result['skipped']);
      $errors = array_merge($errors, $result['errors']);
    }

    // Create media types.
    if (!empty($components['media_types']) && ($componentFilter === NULL || in_array('media_types', $componentFilter))) {
      $result = $this->createMediaTypes($components['media_types'], $skipExisting);
      $created = array_merge($created, $result['created']);
      $skipped = array_merge($skipped, $result['skipped']);
      $errors = array_merge($errors, $result['errors']);
    }

    // Create webforms.
    if (!empty($components['webforms']) && ($componentFilter === NULL || in_array('webforms', $componentFilter))) {
      $result = $this->createWebforms($components['webforms'], $skipExisting);
      $created = array_merge($created, $result['created']);
      $skipped = array_merge($skipped, $result['skipped']);
      $errors = array_merge($errors, $result['errors']);
    }

    // Create views.
    if (!empty($components['views']) && ($componentFilter === NULL || in_array('views', $componentFilter))) {
      $result = $this->createViews($components['views'], $skipExisting);
      $created = array_merge($created, $result['created']);
      $skipped = array_merge($skipped, $result['skipped']);
      $errors = array_merge($errors, $result['errors']);
    }

    $success = empty($errors);

    $this->auditLogger->log(
      $success ? 'success' : 'partial',
      'apply_template',
      'template',
      $id,
      [
        'template' => $id,
        'created_count' => count($created),
        'skipped_count' => count($skipped),
        'error_count' => count($errors),
      ]
    );

    return [
      'success' => $success,
      'data' => [
        'template' => $id,
        'created' => $created,
        'skipped' => $skipped,
        'errors' => $errors,
        'message' => $success
          ? sprintf('Template "%s" applied successfully. Created %d components.', $template['label'], count($created))
          : sprintf('Template "%s" applied with errors. Created %d, errors: %d.', $template['label'], count($created), count($errors)),
      ],
    ];
  }

  /**
   * Export current site configuration as a custom template.
   *
   * @param string $name
   *   The template name.
   * @param array $contentTypes
   *   Content type machine names to include.
   * @param array $vocabularies
   *   Vocabulary machine names to include.
   * @param array $roles
   *   Role machine names to include.
   *
   * @return array
   *   Result array with the exported template definition.
   */
  public function exportAsTemplate(string $name, array $contentTypes, array $vocabularies, array $roles): array {
    // Require admin scope for exporting templates.
    if (!$this->accessManager->canAdmin()) {
      return [
        'success' => FALSE,
        'error' => 'Exporting templates requires admin scope.',
        'code' => 'INSUFFICIENT_SCOPE',
      ];
    }

    // Validate template name (machine name format).
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
      return [
        'success' => FALSE,
        'error' => 'Template name must be a valid machine name (lowercase letters, numbers, underscores, starting with a letter).',
        'code' => 'INVALID_NAME',
      ];
    }

    $template = [
      'id' => $name,
      'label' => ucwords(str_replace('_', ' ', $name)),
      'description' => 'Custom exported template',
      'category' => 'Custom',
      'components' => [],
    ];

    $errors = [];

    // Export content types.
    if (!empty($contentTypes)) {
      $nodeTypeStorage = $this->entityTypeManager->getStorage('node_type');
      $fieldConfigStorage = $this->entityTypeManager->getStorage('field_config');
      $template['components']['content_types'] = [];

      foreach ($contentTypes as $typeId) {
        $nodeType = $nodeTypeStorage->load($typeId);
        if (!$nodeType) {
          $errors[] = sprintf('Content type "%s" not found.', $typeId);
          continue;
        }

        $typeExport = [
          'label' => $nodeType->label(),
          'description' => $nodeType->getDescription(),
          'fields' => [],
        ];

        // Get field configurations.
        $fieldConfigs = $fieldConfigStorage->loadByProperties([
          'entity_type' => 'node',
          'bundle' => $typeId,
        ]);

        foreach ($fieldConfigs as $fieldConfig) {
          $fieldName = $fieldConfig->getName();
          // Skip base fields.
          if (in_array($fieldName, ['title', 'uid', 'status', 'created', 'changed', 'promote', 'sticky'])) {
            continue;
          }

          $typeExport['fields'][$fieldName] = [
            'type' => $fieldConfig->getType(),
            'label' => $fieldConfig->getLabel(),
            'cardinality' => $fieldConfig->getFieldStorageDefinition()->getCardinality(),
          ];

          // Include target info for entity references.
          if ($fieldConfig->getType() === 'entity_reference') {
            $settings = $fieldConfig->getSettings();
            $typeExport['fields'][$fieldName]['target'] = $settings['target_type'] ?? 'node';
          }
        }

        $template['components']['content_types'][$typeId] = $typeExport;
      }
    }

    // Export vocabularies.
    if (!empty($vocabularies)) {
      $vocabStorage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
      $template['components']['vocabularies'] = [];

      foreach ($vocabularies as $vocabId) {
        $vocab = $vocabStorage->load($vocabId);
        if (!$vocab) {
          $errors[] = sprintf('Vocabulary "%s" not found.', $vocabId);
          continue;
        }

        $template['components']['vocabularies'][$vocabId] = [
          'label' => $vocab->label(),
          'description' => $vocab->getDescription(),
        ];
      }
    }

    // Export roles.
    if (!empty($roles)) {
      $roleStorage = $this->entityTypeManager->getStorage('user_role');
      $template['components']['roles'] = [];

      foreach ($roles as $roleId) {
        // Skip built-in roles.
        if (in_array($roleId, ['anonymous', 'authenticated', 'administrator'])) {
          $errors[] = sprintf('Cannot export built-in role "%s".', $roleId);
          continue;
        }

        $role = $roleStorage->load($roleId);
        if (!$role) {
          $errors[] = sprintf('Role "%s" not found.', $roleId);
          continue;
        }

        $template['components']['roles'][$roleId] = [
          'label' => $role->label(),
          'permissions' => $role->getPermissions(),
        ];
      }
    }

    $this->auditLogger->logSuccess('export_template', 'template', $name, [
      'content_types' => $contentTypes,
      'vocabularies' => $vocabularies,
      'roles' => $roles,
    ]);

    return [
      'success' => TRUE,
      'data' => [
        'template' => $template,
        'errors' => $errors,
        'message' => empty($errors)
          ? sprintf('Template "%s" exported successfully.', $name)
          : sprintf('Template "%s" exported with %d warnings.', $name, count($errors)),
      ],
    ];
  }

  /**
   * Get a summary of template components.
   *
   * @param array $components
   *   The components array from a template.
   *
   * @return array
   *   Summary with counts.
   */
  protected function getComponentSummary(array $components): array {
    return [
      'content_types' => count($components['content_types'] ?? []),
      'vocabularies' => count($components['vocabularies'] ?? []),
      'roles' => count($components['roles'] ?? []),
      'views' => count($components['views'] ?? []),
      'media_types' => count($components['media_types'] ?? []),
      'webforms' => count($components['webforms'] ?? []),
    ];
  }

  /**
   * Create vocabularies from template definition.
   *
   * @param array $vocabularies
   *   Vocabulary definitions.
   * @param bool $skipExisting
   *   Whether to skip existing vocabularies.
   *
   * @return array
   *   Result with created, skipped, and errors.
   */
  protected function createVocabularies(array $vocabularies, bool $skipExisting): array {
    $created = [];
    $skipped = [];
    $errors = [];

    $storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');

    foreach ($vocabularies as $vocabId => $config) {
      try {
        $existing = $storage->load($vocabId);
        if ($existing) {
          if ($skipExisting) {
            $skipped[] = ['type' => 'vocabulary', 'id' => $vocabId, 'label' => $config['label']];
            continue;
          }
        }

        $vocab = $storage->create([
          'vid' => $vocabId,
          'name' => $config['label'],
          'description' => $config['description'] ?? '',
        ]);
        $vocab->save();

        $created[] = ['type' => 'vocabulary', 'id' => $vocabId, 'label' => $config['label']];
      }
      catch (\Exception $e) {
        $errors[] = ['type' => 'vocabulary', 'id' => $vocabId, 'error' => $e->getMessage()];
      }
    }

    return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
  }

  /**
   * Create roles from template definition.
   *
   * @param array $roles
   *   Role definitions.
   * @param bool $skipExisting
   *   Whether to skip existing roles.
   *
   * @return array
   *   Result with created, skipped, and errors.
   */
  protected function createRoles(array $roles, bool $skipExisting): array {
    $created = [];
    $skipped = [];
    $errors = [];

    $storage = $this->entityTypeManager->getStorage('user_role');

    foreach ($roles as $roleId => $config) {
      try {
        $existing = $storage->load($roleId);
        if ($existing) {
          if ($skipExisting) {
            $skipped[] = ['type' => 'role', 'id' => $roleId, 'label' => $config['label']];
            continue;
          }
        }

        $role = $storage->create([
          'id' => $roleId,
          'label' => $config['label'],
        ]);
        $role->save();

        // Grant permissions.
        if (!empty($config['permissions'])) {
          foreach ($config['permissions'] as $permission) {
            $role->grantPermission($permission);
          }
          $role->save();
        }

        $created[] = ['type' => 'role', 'id' => $roleId, 'label' => $config['label']];
      }
      catch (\Exception $e) {
        $errors[] = ['type' => 'role', 'id' => $roleId, 'error' => $e->getMessage()];
      }
    }

    return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
  }

  /**
   * Create content types from template definition.
   *
   * @param array $contentTypes
   *   Content type definitions.
   * @param bool $skipExisting
   *   Whether to skip existing content types.
   *
   * @return array
   *   Result with created, skipped, and errors.
   */
  protected function createContentTypes(array $contentTypes, bool $skipExisting): array {
    $created = [];
    $skipped = [];
    $errors = [];

    $nodeTypeStorage = $this->entityTypeManager->getStorage('node_type');

    foreach ($contentTypes as $typeId => $config) {
      try {
        $existing = $nodeTypeStorage->load($typeId);
        if ($existing) {
          if ($skipExisting) {
            $skipped[] = ['type' => 'content_type', 'id' => $typeId, 'label' => $config['label']];
            continue;
          }
        }

        // Create content type.
        $nodeType = $nodeTypeStorage->create([
          'type' => $typeId,
          'name' => $config['label'],
          'description' => $config['description'] ?? '',
        ]);
        $nodeType->save();

        // Create fields.
        if (!empty($config['fields'])) {
          $this->createFields('node', $typeId, $config['fields']);
        }

        $created[] = ['type' => 'content_type', 'id' => $typeId, 'label' => $config['label']];
      }
      catch (\Exception $e) {
        $errors[] = ['type' => 'content_type', 'id' => $typeId, 'error' => $e->getMessage()];
      }
    }

    return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
  }

  /**
   * Create fields for an entity bundle.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param array $fields
   *   Field definitions.
   */
  protected function createFields(string $entityType, string $bundle, array $fields): void {
    $fieldStorageStorage = $this->entityTypeManager->getStorage('field_storage_config');
    $fieldConfigStorage = $this->entityTypeManager->getStorage('field_config');

    foreach ($fields as $fieldName => $fieldConfig) {
      // Check if storage exists.
      $storageId = $entityType . '.' . $fieldName;
      $storage = $fieldStorageStorage->load($storageId);

      if (!$storage) {
        // Create field storage.
        $storageConfig = [
          'field_name' => $fieldName,
          'entity_type' => $entityType,
          'type' => $fieldConfig['type'],
          'cardinality' => $fieldConfig['cardinality'] ?? 1,
        ];

        // Handle entity reference target type.
        if ($fieldConfig['type'] === 'entity_reference' && !empty($fieldConfig['target'])) {
          $targetParts = explode(':', $fieldConfig['target']);
          $storageConfig['settings'] = [
            'target_type' => $targetParts[0] === 'taxonomy_term' ? 'taxonomy_term' : ($targetParts[0] === 'node' ? 'node' : $targetParts[0]),
          ];
        }

        // Handle list fields.
        if ($fieldConfig['type'] === 'list_string' && !empty($fieldConfig['allowed_values'])) {
          $allowedValues = [];
          foreach ($fieldConfig['allowed_values'] as $value) {
            $allowedValues[$value] = $value;
          }
          $storageConfig['settings'] = ['allowed_values' => $allowedValues];
        }

        $storage = $fieldStorageStorage->create($storageConfig);
        $storage->save();
      }

      // Create field config for this bundle.
      $fieldId = $entityType . '.' . $bundle . '.' . $fieldName;
      $existingField = $fieldConfigStorage->load($fieldId);

      if (!$existingField) {
        $configData = [
          'field_name' => $fieldName,
          'entity_type' => $entityType,
          'bundle' => $bundle,
          'label' => $fieldConfig['label'],
        ];

        // Handle entity reference handler settings.
        if ($fieldConfig['type'] === 'entity_reference' && !empty($fieldConfig['target'])) {
          $targetParts = explode(':', $fieldConfig['target']);
          $targetType = $targetParts[0];
          $targetBundle = $targetParts[1] ?? NULL;

          if ($targetType === 'taxonomy_term' && $targetBundle) {
            $configData['settings'] = [
              'handler' => 'default:taxonomy_term',
              'handler_settings' => [
                'target_bundles' => [$targetBundle => $targetBundle],
              ],
            ];
          }
          elseif ($targetType === 'node' && $targetBundle) {
            $configData['settings'] = [
              'handler' => 'default:node',
              'handler_settings' => [
                'target_bundles' => [$targetBundle => $targetBundle],
              ],
            ];
          }
        }

        $field = $fieldConfigStorage->create($configData);
        $field->save();

        // Configure form and view display.
        $this->configureFieldDisplay($entityType, $bundle, $fieldName, $fieldConfig);
      }
    }
  }

  /**
   * Configure field display for form and view modes.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $fieldName
   *   The field name.
   * @param array $fieldConfig
   *   The field configuration.
   */
  protected function configureFieldDisplay(string $entityType, string $bundle, string $fieldName, array $fieldConfig): void {
    try {
      // Configure form display.
      $formDisplayStorage = $this->entityTypeManager->getStorage('entity_form_display');
      $formDisplayId = $entityType . '.' . $bundle . '.default';
      $formDisplay = $formDisplayStorage->load($formDisplayId);

      if (!$formDisplay) {
        $formDisplay = $formDisplayStorage->create([
          'targetEntityType' => $entityType,
          'bundle' => $bundle,
          'mode' => 'default',
          'status' => TRUE,
        ]);
      }

      $formDisplay->setComponent($fieldName, [
        'weight' => 10,
      ]);
      $formDisplay->save();

      // Configure view display.
      $viewDisplayStorage = $this->entityTypeManager->getStorage('entity_view_display');
      $viewDisplayId = $entityType . '.' . $bundle . '.default';
      $viewDisplay = $viewDisplayStorage->load($viewDisplayId);

      if (!$viewDisplay) {
        $viewDisplay = $viewDisplayStorage->create([
          'targetEntityType' => $entityType,
          'bundle' => $bundle,
          'mode' => 'default',
          'status' => TRUE,
        ]);
      }

      $viewDisplay->setComponent($fieldName, [
        'weight' => 10,
        'label' => 'above',
      ]);
      $viewDisplay->save();
    }
    catch (\Exception $e) {
      // Display configuration is non-critical.
      \Drupal::logger('mcp_tools_templates')->warning(
        'Failed to configure display for field @field: @error',
        ['@field' => $fieldName, '@error' => $e->getMessage()]
      );
    }
  }

  /**
   * Create media types from template definition.
   *
   * @param array $mediaTypes
   *   Media type definitions.
   * @param bool $skipExisting
   *   Whether to skip existing media types.
   *
   * @return array
   *   Result with created, skipped, and errors.
   */
  protected function createMediaTypes(array $mediaTypes, bool $skipExisting): array {
    $created = [];
    $skipped = [];
    $errors = [];

    try {
      $storage = $this->entityTypeManager->getStorage('media_type');
    }
    catch (\Exception $e) {
      $errors[] = ['type' => 'media_type', 'id' => '*', 'error' => 'Media module not installed.'];
      return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }

    foreach ($mediaTypes as $typeId => $config) {
      try {
        $existing = $storage->load($typeId);
        if ($existing) {
          if ($skipExisting) {
            $skipped[] = ['type' => 'media_type', 'id' => $typeId, 'label' => $config['label']];
            continue;
          }
        }

        $mediaType = $storage->create([
          'id' => $typeId,
          'label' => $config['label'],
          'description' => $config['description'] ?? '',
          'source' => $config['source'] ?? 'image',
        ]);
        $mediaType->save();

        $created[] = ['type' => 'media_type', 'id' => $typeId, 'label' => $config['label']];
      }
      catch (\Exception $e) {
        $errors[] = ['type' => 'media_type', 'id' => $typeId, 'error' => $e->getMessage()];
      }
    }

    return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
  }

  /**
   * Create webforms from template definition.
   *
   * @param array $webforms
   *   Webform definitions.
   * @param bool $skipExisting
   *   Whether to skip existing webforms.
   *
   * @return array
   *   Result with created, skipped, and errors.
   */
  protected function createWebforms(array $webforms, bool $skipExisting): array {
    $created = [];
    $skipped = [];
    $errors = [];

    try {
      $storage = $this->entityTypeManager->getStorage('webform');
    }
    catch (\Exception $e) {
      $errors[] = ['type' => 'webform', 'id' => '*', 'error' => 'Webform module not installed.'];
      return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }

    foreach ($webforms as $webformId => $config) {
      try {
        $existing = $storage->load($webformId);
        if ($existing) {
          if ($skipExisting) {
            $skipped[] = ['type' => 'webform', 'id' => $webformId, 'label' => $config['label']];
            continue;
          }
        }

        // Build elements YAML.
        $elements = [];
        foreach ($config['elements'] ?? [] as $elementId => $elementConfig) {
          $elements[$elementId] = [
            '#type' => $elementConfig['type'],
            '#title' => $elementConfig['title'],
          ];
          if (!empty($elementConfig['required'])) {
            $elements[$elementId]['#required'] = TRUE;
          }
        }

        $webform = $storage->create([
          'id' => $webformId,
          'title' => $config['label'],
          'description' => $config['description'] ?? '',
          'elements' => \Symfony\Component\Yaml\Yaml::dump($elements),
          'status' => 'open',
        ]);
        $webform->save();

        $created[] = ['type' => 'webform', 'id' => $webformId, 'label' => $config['label']];
      }
      catch (\Exception $e) {
        $errors[] = ['type' => 'webform', 'id' => $webformId, 'error' => $e->getMessage()];
      }
    }

    return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
  }

  /**
   * Create views from template definition.
   *
   * @param array $views
   *   View definitions.
   * @param bool $skipExisting
   *   Whether to skip existing views.
   *
   * @return array
   *   Result with created, skipped, and errors.
   */
  protected function createViews(array $views, bool $skipExisting): array {
    $created = [];
    $skipped = [];
    $errors = [];

    $storage = $this->entityTypeManager->getStorage('view');

    foreach ($views as $viewId => $config) {
      try {
        $existing = $storage->load($viewId);
        if ($existing) {
          if ($skipExisting) {
            $skipped[] = ['type' => 'view', 'id' => $viewId, 'label' => $config['label']];
            continue;
          }
        }

        // Build view configuration.
        $viewConfig = [
          'id' => $viewId,
          'label' => $config['label'],
          'description' => $config['description'] ?? '',
          'base_table' => $config['base_table'] ?? 'node_field_data',
          'display' => [
            'default' => [
              'display_plugin' => 'default',
              'id' => 'default',
              'display_title' => 'Default',
              'position' => 0,
              'display_options' => [
                'title' => $config['label'],
                'pager' => [
                  'type' => 'some',
                  'options' => [
                    'items_per_page' => $config['pager'] ?? 10,
                  ],
                ],
                'style' => [
                  'type' => $config['style'] ?? 'default',
                ],
                'row' => [
                  'type' => 'fields',
                ],
              ],
            ],
          ],
        ];

        // Add page display if configured.
        if (!empty($config['display']['page'])) {
          $viewConfig['display']['page_1'] = [
            'display_plugin' => 'page',
            'id' => 'page_1',
            'display_title' => 'Page',
            'position' => 1,
            'display_options' => [
              'path' => ltrim($config['display']['page'], '/'),
            ],
          ];
        }

        // Add block display if configured.
        if (!empty($config['display']['block'])) {
          $viewConfig['display']['block_1'] = [
            'display_plugin' => 'block',
            'id' => 'block_1',
            'display_title' => 'Block',
            'position' => 2,
          ];
        }

        $view = $storage->create($viewConfig);
        $view->save();

        $created[] = ['type' => 'view', 'id' => $viewId, 'label' => $config['label']];
      }
      catch (\Exception $e) {
        $errors[] = ['type' => 'view', 'id' => $viewId, 'error' => $e->getMessage()];
      }
    }

    return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
  }

}
