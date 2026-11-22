# MCP Tools - Media

Media type and entity management for MCP Tools.

## Tools (7)

| Tool | Description |
|------|-------------|
| `mcp_create_media_type` | Create media types |
| `mcp_delete_media_type` | Remove media types |
| `mcp_upload_file` | Upload files (base64 support) |
| `mcp_create_media` | Create media entities |
| `mcp_delete_media` | Delete media entities |
| `mcp_list_media_types` | List available media types |
| `mcp_generate_alt_text` | Write the alt text of an image with the client's own model (needs a client that supports MCP sampling) |

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

AI calls: mcp_upload_file(
  filename: "logo.png",
  data: "base64-encoded-data...",
  directory: "public://logos"
)
```

### Create Media Entity

```
User: "Create a media entity for the uploaded image"

AI calls: mcp_create_media(
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

AI calls: mcp_create_media_type(
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

AI calls: mcp_list_media_types()

Returns: image, document, video, audio, remote_video
```

## Safety Features

- **File validation:** MIME types and extensions validated
- **Directory permissions:** Files only uploaded to allowed directories
- **Size limits:** Respects Drupal's upload size limits
- **Audit logging:** All media operations logged
