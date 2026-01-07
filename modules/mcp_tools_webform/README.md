# MCP Tools - Webform

Webform creation and submission management for MCP Tools.

## Tools (7)

| Tool | Description |
|------|-------------|
| `mcp_webform_list` | List all webforms |
| `mcp_webform_get` | Get webform details |
| `mcp_webform_get_submissions` | Get form submissions |
| `mcp_webform_create` | Create new webforms |
| `mcp_webform_update` | Update webform settings |
| `mcp_webform_delete` | Delete webforms |
| `mcp_webform_delete_submission` | Delete individual submissions |

## Requirements

- mcp_tools (base module)
- webform:webform

## Installation

```bash
drush en mcp_tools_webform
```

## Example Usage

### Create a Contact Form

```
User: "Create a contact form"

AI calls: mcp_webform_create(
  id: "contact",
  title: "Contact Us",
  elements: {
    name: {
      "#type": "textfield",
      "#title": "Your Name",
      "#required": true
    },
    email: {
      "#type": "email",
      "#title": "Email Address",
      "#required": true
    },
    subject: {
      "#type": "textfield",
      "#title": "Subject",
      "#required": true
    },
    message: {
      "#type": "textarea",
      "#title": "Message",
      "#required": true
    }
  }
)
```

### Create a Survey Form

```
User: "Create a customer satisfaction survey"

AI calls: mcp_webform_create(
  id: "satisfaction_survey",
  title: "Customer Satisfaction",
  elements: {
    rating: {
      "#type": "radios",
      "#title": "How satisfied are you?",
      "#options": {
        1: "Very Dissatisfied",
        2: "Dissatisfied",
        3: "Neutral",
        4: "Satisfied",
        5: "Very Satisfied"
      },
      "#required": true
    },
    feedback: {
      "#type": "textarea",
      "#title": "Additional Feedback"
    }
  }
)
```

### View Submissions

```
User: "Show me recent contact form submissions"

AI calls: mcp_webform_get_submissions(
  webform_id: "contact",
  limit: 10
)
```

## Supported Element Types

- `textfield` - Single-line text
- `textarea` - Multi-line text
- `email` - Email address
- `tel` - Telephone number
- `number` - Numeric input
- `select` - Dropdown
- `radios` - Radio buttons
- `checkboxes` - Checkbox group
- `checkbox` - Single checkbox
- `date` - Date picker
- `hidden` - Hidden field

## Safety Features

- **Submission privacy:** Sensitive submission data handled carefully
- **Element validation:** Form elements validated before save
- **Audit logging:** All webform operations logged
