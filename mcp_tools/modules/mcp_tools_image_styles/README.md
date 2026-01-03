# MCP Tools - Image Styles

Manage Drupal image styles and effects via MCP.

## Tools (7)

| Tool | Description |
|------|-------------|
| `mcp_image_styles_list` | List all image styles with their effects |
| `mcp_image_styles_get` | Get details of a specific image style |
| `mcp_image_styles_create` | Create a new image style |
| `mcp_image_styles_delete` | Delete an image style |
| `mcp_image_styles_add_effect` | Add an effect to an image style |
| `mcp_image_styles_remove_effect` | Remove an effect from an image style |
| `mcp_image_styles_list_effects` | List available image effect plugins |

## Requirements

- mcp_tools (base module)
- drupal:image (core)

## Usage Examples

### Create a thumbnail style with scale effect

```
1. mcp_image_styles_create(id: "thumbnail", label: "Thumbnail")
2. mcp_image_styles_add_effect(
     style_id: "thumbnail",
     effect_id: "image_scale",
     configuration: {width: 150, height: 150, upscale: false}
   )
```

### Add crop effect to existing style

```
mcp_image_styles_add_effect(
  style_id: "medium",
  effect_id: "image_scale_and_crop",
  configuration: {width: 400, height: 300}
)
```

### Common image effects

| Effect ID | Description | Configuration |
|-----------|-------------|---------------|
| `image_scale` | Scale maintaining aspect ratio | `{width, height, upscale}` |
| `image_scale_and_crop` | Scale and center crop | `{width, height}` |
| `image_crop` | Crop to exact dimensions | `{width, height, anchor}` |
| `image_resize` | Resize without maintaining ratio | `{width, height}` |
| `image_rotate` | Rotate image | `{degrees, bgcolor, random}` |
| `image_desaturate` | Convert to grayscale | `{}` |
| `image_convert` | Convert image format | `{extension}` |

## Security

- All write operations require appropriate scope
- All changes are audit logged
- Usage checking prevents deleting styles in use (unless force=true)
