services:
  {{ machine_name }}.mcp_components:
    class: Drupal\{{ machine_name }}\Mcp\{{ provider_class }}
    tags:
      - { name: mcp_tools.component_provider }
