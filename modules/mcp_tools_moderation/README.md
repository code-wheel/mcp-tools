# MCP Tools - Content Moderation

Content moderation workflow tools for MCP Tools. Provides integration with Drupal's core Content Moderation module for managing editorial workflows, moderation states, and content transitions.

## Tools (6)

| Tool | Description |
|------|-------------|
| `mcp_moderation_get_workflows` | List all content moderation workflows with states and transitions |
| `mcp_moderation_get_workflow` | Get details of a specific workflow |
| `mcp_moderation_get_state` | Get current moderation state of an entity |
| `mcp_moderation_set_state` | Set moderation state (creates revision) |
| `mcp_moderation_get_history` | Get revision history with moderation states |
| `mcp_moderation_get_content_by_state` | List content in a specific state |

## Requirements

- mcp_tools (base module)
- drupal:content_moderation (core)
- drupal:workflows (core)

## Installation

```bash
drush en mcp_tools_moderation
```

## Example Usage

### List Available Workflows

```
User: "What editorial workflows are configured?"

AI calls: mcp_moderation_get_workflows()

Response:
{
  "success": true,
  "data": {
    "total": 1,
    "workflows": [
      {
        "id": "editorial",
        "label": "Editorial",
        "states": {
          "draft": {"id": "draft", "label": "Draft", "published": false},
          "review": {"id": "review", "label": "In Review", "published": false},
          "published": {"id": "published", "label": "Published", "published": true}
        },
        "transitions": {
          "create_new_draft": {"from": ["draft", "published"], "to": "draft"},
          "submit_for_review": {"from": ["draft"], "to": "review"},
          "publish": {"from": ["review"], "to": "published"}
        }
      }
    ]
  }
}
```

### Check Content Moderation State

```
User: "What's the moderation state of node 123?"

AI calls: mcp_moderation_get_state(
  entity_type: "node",
  entity_id: 123
)

Response:
{
  "success": true,
  "data": {
    "entity_type": "node",
    "entity_id": 123,
    "label": "My Article",
    "bundle": "article",
    "workflow_id": "editorial",
    "current_state": {
      "id": "draft",
      "label": "Draft",
      "published": false
    },
    "available_transitions": [
      {"id": "submit_for_review", "label": "Submit for Review", "to_state": "review"}
    ]
  }
}
```

### Transition Content to Review

```
User: "Submit article 123 for review"

AI calls: mcp_moderation_set_state(
  entity_type: "node",
  entity_id: 123,
  state: "review",
  revision_message: "Submitted for editorial review"
)

Response:
{
  "success": true,
  "data": {
    "entity_type": "node",
    "entity_id": 123,
    "label": "My Article",
    "previous_state": "draft",
    "new_state": "review",
    "new_state_label": "In Review",
    "is_published": false,
    "changed": true,
    "message": "Moderation state changed from 'draft' to 'review'."
  }
}
```

### Publish Content

```
User: "Publish article 123"

AI calls: mcp_moderation_set_state(
  entity_type: "node",
  entity_id: 123,
  state: "published"
)

Response:
{
  "success": true,
  "data": {
    "entity_type": "node",
    "entity_id": 123,
    "label": "My Article",
    "previous_state": "review",
    "new_state": "published",
    "new_state_label": "Published",
    "is_published": true,
    "changed": true,
    "message": "Moderation state changed from 'review' to 'published'."
  }
}
```

### View Moderation History

```
User: "Show the revision history for article 123"

AI calls: mcp_moderation_get_history(
  entity_type: "node",
  entity_id: 123,
  limit: 10
)

Response:
{
  "success": true,
  "data": {
    "entity_type": "node",
    "entity_id": 123,
    "label": "My Article",
    "workflow_id": "editorial",
    "total_revisions": 3,
    "revisions": [
      {
        "revision_id": 789,
        "moderation_state": "published",
        "moderation_state_label": "Published",
        "is_published": true,
        "is_current": true,
        "revision_log": "Published via MCP Tools",
        "revision_created": "2024-01-15 14:30:00"
      },
      {
        "revision_id": 456,
        "moderation_state": "review",
        "moderation_state_label": "In Review",
        "is_published": false,
        "is_current": false,
        "revision_log": "Submitted for editorial review",
        "revision_created": "2024-01-14 10:00:00"
      },
      {
        "revision_id": 123,
        "moderation_state": "draft",
        "moderation_state_label": "Draft",
        "is_published": false,
        "is_current": false,
        "revision_log": "Initial draft",
        "revision_created": "2024-01-13 09:00:00"
      }
    ]
  }
}
```

### Find Content Awaiting Review

```
User: "Show me all content waiting for review"

AI calls: mcp_moderation_get_content_by_state(
  workflow_id: "editorial",
  state: "review",
  limit: 50
)

Response:
{
  "success": true,
  "data": {
    "workflow_id": "editorial",
    "workflow_label": "Editorial",
    "state": "review",
    "state_label": "In Review",
    "state_published": false,
    "total": 5,
    "content": [
      {
        "entity_type": "node",
        "entity_id": 125,
        "bundle": "article",
        "label": "New Product Announcement",
        "url": "/node/125",
        "changed": "2024-01-15 12:00:00"
      },
      {
        "entity_type": "node",
        "entity_id": 126,
        "bundle": "page",
        "label": "About Us Update",
        "url": "/about-us",
        "changed": "2024-01-15 11:30:00"
      }
    ]
  }
}
```

## Common Workflow States

The default "Editorial" workflow includes:

| State | Description | Published |
|-------|-------------|-----------|
| `draft` | Content is being worked on | No |
| `review` | Content is pending editorial review | No |
| `published` | Content is live on the site | Yes |
| `archived` | Content is no longer active (optional) | No |

## Common Transitions

| Transition | From | To |
|------------|------|-----|
| Create New Draft | draft, published | draft |
| Submit for Review | draft | review |
| Publish | review | published |
| Unpublish | published | draft |
| Archive | published | archived |

## Safety Features

- **Transition validation:** Only valid transitions allowed based on workflow configuration
- **Permission checks:** User permissions validated before state changes
- **Revision tracking:** All state changes create new revisions with log messages
- **Audit logging:** All write operations logged for accountability

## Write Operations

The following tool requires write access:

- `mcp_moderation_set_state` - Modifies entity moderation state

Write access must be enabled in MCP Tools configuration. All write operations are logged.
