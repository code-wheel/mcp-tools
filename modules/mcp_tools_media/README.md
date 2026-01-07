# MCP Tools - Media

Media type and entity management for MCP Tools.

## Tools (6)

| Tool | Description |
|------|-------------|
| `mcp_media_create_type` | Create media types |
| `mcp_media_delete_type` | Remove media types |
| `mcp_media_upload_file` | Upload files (base64 support) |
| `mcp_media_create` | Create media entities |
| `mcp_media_delete` | Delete media entities |
| `mcp_media_list_types` | List available media types |

## Requirements

- mcp_tools (base module)
- drupal:media

## Installation

```bash
drush en mcp_tools_media
```

## Example Usage

### Upload an Image

```
User: "Upload this logo image"

AI calls: mcp_media_upload_file(
  filename: "logo.png",
  data: "base64-encoded-data...",
  directory: "public://logos"
)
```

### Create Media Entity

```
User: "Create a media entity for the uploaded image"

AI calls: mcp_media_create(
  bundle: "image",
  name: "Company Logo",
  field_media_image: {
    target_id: 123,
    alt: "Company Logo"
  }
)
```

### Create Custom Media Type

```
User: "Create a media type for PDFs"

AI calls: mcp_media_create_type(
  id: "document",
  label: "Document",
  source: "file",
  source_configuration: {
    source_field: "field_media_document"
  }
)
```

### List Media Types

```
User: "What media types are available?"

AI calls: mcp_media_list_types()

Returns: image, document, video, audio, remote_video
```

## Safety Features

- **File validation:** MIME types and extensions validated
- **Directory permissions:** Files only uploaded to allowed directories
- **Size limits:** Respects Drupal's upload size limits
- **Audit logging:** All media operations logged
