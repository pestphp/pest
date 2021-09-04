---
body:
    - type: radio
      id: type
      attributes:
        label: Select one type
        options:
            - Bug fix
            - New feature
      validations:
        required: false
    - type: input
      id: issue
      attributes:
        label: Fixed tickets
        description: prefixed issue number(s), if any
        value: \#
    - type: markdown
      attributes:
        label: Description
---
