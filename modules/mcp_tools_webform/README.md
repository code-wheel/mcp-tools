# MCP Tools - Webform

Webform creation and submission management for MCP Tools.

## Tools (7)

| Tool | Description |
|------|-------------|
| `mcp_list_webforms` | List all webforms |
| `mcp_get_webform` | Get webform details |
| `mcp_get_webform_submissions` | Get form submissions |
| `mcp_create_webform` | Create new webforms |
| `mcp_update_webform` | Update webform settings |
| `mcp_delete_webform` | Delete webforms |
| `mcp_delete_webform_submission` | Delete individual submissions |

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

AI calls: mcp_create_webform(
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

AI calls: mcp_create_webform(
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

AI calls: mcp_get_webform_submissions(
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
