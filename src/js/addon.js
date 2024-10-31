/* global jQuery, gform */

class GFEmailVerificationAddon {
  constructor() {
    this.localizedData = window.rfs_gf_ev_addon_strings;
    this.defaultLabel = this.localizedData.label;
    this.type = 'email-verification-code';

    if (!gform) {
      return;
    }

    this.gform = gform;

    this.selectors = {
      fields: '.gform_fields',
      label: '.gfield_label',
      vcField: '.gfield--type-email-verification-code',
    };

    this.setupField();
    this.lockFieldType();
  }

  /**
   * Setup the field when it is added to the form - set default label, display alert if needed
   */
  setupField() {
    jQuery(document).on('gform_field_added', (event, form, field) => {
      if (field.type === this.type) {
        const codeField = document.querySelector(
          `${this.selectors.fields} #field_${field.id}`,
        );
        const emailField = document.querySelector(
          `${this.selectors.fields} input[type="email"]`,
        );

        if (codeField) {
          const codeFieldLabel = codeField.querySelector(this.selectors.label);

          field.label = this.defaultLabel;
          codeFieldLabel.innerText = this.defaultLabel;

          if (!emailField) {
            setTimeout(() => {
              alert(this.localizedData.emailReminder);
            }, 500);
          }
        }
      }
    });
  }

  /**
   * Make sure the field is added only once into the form.
   */
  lockFieldType() {
    this.gform.addFilter(
      'gform_form_editor_can_field_be_added',
      (canFieldBeAdded, type) => {
        if (type === this.type) {
          const codefield = document.querySelector(
            `${this.selectors.fields} ${this.selectors.vcField}`,
          );

          if (codefield) {
            alert(this.localizedData.alert);
            return false;
          }
        }

        return canFieldBeAdded;
      },
    );
  }
}

// eslint-disable-next-line no-new
new GFEmailVerificationAddon();
