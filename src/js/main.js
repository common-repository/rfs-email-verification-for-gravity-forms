/* global jQuery */

const GFEmailVerificationState = {
  codeSent: [],
  showCodeSentMessage: false,
  cookiesCleared: false,
};

class GFEmailVerification {
  constructor(formId, currentPage) {
    this.formId = formId;
    this.localizedData =
      window?.[`RFS_GF_EMAIL_VERIFICATION_FORM_${this.formId}`];

    if (!this.localizedData) {
      return;
    }

    this.form = document.querySelector(
      `.gform_wrapper form#gform_${formId}.is-vc-form`,
    );

    if (!this.form) {
      return;
    }

    this.currentPage = parseFloat(currentPage);
    this.formInfo = this.localizedData.formInfo;
    this.state = GFEmailVerificationState;

    if (!this.formInfo?.id) {
      return;
    }

    if (this.formInfo.code_sent) {
      this.state.codeSent.push(this.formId);
      this.state.showCodeSentMessage = true;
    }

    this.emailFieldData = this.formInfo.fields.email;
    this.codeFieldData = this.formInfo.fields['email-verification-code'];

    this.selectors = {
      page: '.gform_page',
      gField: '.gfield',
      sendButton: '.js-gform-vc-button',
      sendButtonLabel: '.gform_vc_button_label',
      nextButton: '.gform_next_button',
      validationMessage: '.validation_message',
      emailField: `input[name="input_${this.emailFieldData.id}"]`,
      codeField: `input[name="input_${this.codeFieldData.id}"]`,
      codeSentMessage: '.gform-vc-message',
      formFooter: '.gform_footer',
      formPageFooter: '.gform_page_footer',
      fatalErrorMessage: '.gform-fatal-error',
    };

    this.classNames = {
      show: 'show',
      hide: 'hide',
      loading: 'is-loading',
      locked: 'is-locked',
      codeSentMessage: 'gform-vc-message',
      gFieldError: 'gfield_error',
      fatalErrorMessage: 'gform-fatal-error',
      ajaxForm: 'is-ajax-form',
    };

    this.init();
  }

  /**
   * Initialize
   */
  init() {
    if (
      !this.state.cookiesCleared &&
      this.form.classList.contains(this.classNames.ajaxForm)
    ) {
      this.ajaxRequest({}, 'clear_cookies').then((result) => {
        const { success, data } = result;

        if (success) {
          this.addonInit();
        } else if (data?.fatal) {
          this.addonInit(data.fatal);
        }

        this.state.cookiesCleared = true;
      });
    } else {
      this.addonInit();
    }
  }

  /**
   * Initialize add-on
   */
  addonInit(error = false) {
    this.form.classList.add(this.classNames.show);

    this.setDOM();

    if (this.emailFieldData.page !== this.codeFieldData.page) {
      return;
    }

    this.setSendButton();
    this.displayCodeSentMessage();

    if (error) {
      this.showFatalError(error['98']);
    }
  }

  /**
   * Set DOM elements
   */
  setDOM() {
    this.page = this.form.querySelectorAll(this.selectors.page);

    if (this.page.length) {
      this.container = this.form.querySelector(
        `#gform_page_${this.formId}_${this.currentPage}`,
      );
    } else {
      this.container = this.form;
    }

    if (!this.container) {
      return;
    }

    if (this.page.length) {
      this.formFooter = this.container.querySelector(
        this.selectors.formPageFooter,
      );
    } else {
      this.formFooter = this.container.querySelector(this.selectors.formFooter);
    }

    this.hiddenField = this.form.querySelector('input[name="gform_vc"]');
    this.codeField = this.form.querySelector(this.selectors.codeField);
    this.emailField = this.form.querySelector(this.selectors.emailField);

    if (!this.emailField || !this.codeField || !this.hiddenField) {
      return;
    }

    this.hiddenField.value = 'evc';

    this.emailFieldContainer = this.emailField.closest(this.selectors.gField);
    this.codeFieldContainer = this.codeField.closest(this.selectors.gField);

    this.sendButton = this.container.querySelector(this.selectors.sendButton);
    this.nextButton = this.container.querySelector(this.selectors.nextButton);
  }

  /**
   * Set logic variables
   */
  setFormRules() {
    const { display, behaviour } = this.sendButton.dataset;

    this.sendButtonDisplay = display;
    this.sendButtonBehaviour = behaviour;

    this.fieldsSamePage = this.emailFieldData.page === this.codeFieldData.page;
    this.isEmailFieldPage = this.emailFieldData.page === this.currentPage;
    this.isCodeFieldPage = this.codeFieldData.page === this.currentPage;

    this.emailFieldOnCurrentPage = this.container.querySelector(
      this.selectors.emailField,
    );
    this.codeFieldOnCurrentPage = this.container.querySelector(
      this.selectors.codeField,
    );

    this.isSendCodeOnNextPage =
      this.sendButtonBehaviour === 'next-page' &&
      this.sendButtonDisplay === 'email-page' &&
      this.formInfo.pagination;

    this.showEmailCodeSentMessage =
      this.sendButtonDisplay === 'email-page' &&
      !this.fieldsSamePage &&
      this.isEmailFieldPage;

    this.showSendButton =
      this.sendButtonDisplay === 'email-page'
        ? this.codeFieldOnCurrentPage || this.emailFieldOnCurrentPage
        : this.codeFieldOnCurrentPage;

    this.codeSentOnNext =
      this.isSendCodeOnNextPage &&
      this.currentPage === this.emailFieldData.page + 1;

    if (this.state.codeSent.includes(this.formId)) {
      this.showSendButton = false;
    }
  }

  /**
   * Setup the send code button (conditional display)
   */
  setSendButton() {
    if (!this.sendButton) {
      return;
    }
    this.setFormRules();

    if (this.showSendButton) {
      this.sendButtonEventHandler();
      this.sendButton.classList.add(this.classNames.show);
    } else {
      this.sendButton.remove();
    }
  }

  /**
   * Send ajax request on button click in cases where the button behaviour is default and for the resend button
   */
  sendButtonEventHandler() {
    this.sendButton.addEventListener('click', () => {
      if (this.isLoading) {
        return;
      }

      this.lockButtons();
      this.clearEmailError();
      this.clearFatalError();

      if (this.codeSentMessage) {
        this.codeSentMessage.remove();
      }

      this.ajaxRequest(
        {
          formId: this.formId,
          email: this.emailField.value,
          hiddenField: this.hiddenField.value,
        },
        'send_verification_code',
      ).then((result) => {
        const { success, data } = result;

        if (success) {
          this.state.codeSent.push(this.formId);
          this.state.showCodeSentMessage = true;

          this.isLoading = false;
          this.unlockButtons();
          this.displayCodeSentMessage();

          this.sendButton.remove();
        } else if (data?.email) {
          this.isLoading = false;
          this.unlockButtons();
          this.showEmailError(data.email);
        } else if (data?.fatal) {
          this.isLoading = false;
          this.unlockButtons();
          this.showFatalError(data.fatal['99']);
        }
      });

      this.isLoading = true;
    });
  }

  /**
   * Clear email field error
   */
  clearEmailError = () => {
    const errorElement = this.emailFieldContainer.querySelector(
      this.selectors.validationMessage,
    );

    if (errorElement) {
      errorElement.remove();
      this.emailFieldContainer.classList.remove(this.classNames.gFieldError);
    }
  };

  /**
   * Display email field error
   */
  showEmailError = (message) => {
    let errorElement = this.emailFieldContainer.querySelector(
      this.selectors.validationMessage,
    );

    if (!errorElement) {
      errorElement = document.createElement('div');
      errorElement.classList.add(
        'gfield_description',
        'validation_message',
        'gfield_validation_message',
      );
      errorElement.setAttribute(
        'id',
        `validation_message_${this.formId}_${this.emailFieldData.id}`,
      );

      this.emailFieldContainer.appendChild(errorElement);
    }

    this.emailFieldContainer.classList.add(this.classNames.gFieldError);
    errorElement.innerText = message;
  };

  /**
   * Display fatal error
   */
  showFatalError = (message) => {
    const errorElement = document.createElement('div');
    errorElement.classList.add(this.classNames.fatalErrorMessage);
    const icon = document.createElement('span');
    icon.classList.add('gform-icon', 'gform-icon--info');
    errorElement.innerText = message;
    errorElement.appendChild(icon);

    this.formFooter.appendChild(errorElement);
  };

  /**
   * Clears fatal error
   */
  clearFatalError = () => {
    const errorElement = this.formFooter.querySelector(
      this.selectors.fatalErrorMessage,
    );

    if (errorElement) {
      errorElement.remove();
    }
  };

  /**
   * Lock buttons during ajax request
   */
  lockButtons() {
    this.sendButton.classList.add(this.classNames.loading);
    this.sendButton.parentElement.classList.add(this.classNames.locked);
    this.sendButton.parentElement
      .querySelectorAll('.button')
      .forEach((button) => button.setAttribute('disabled', true));
  }

  /**
   * Unock buttons after ajax request ended
   */
  unlockButtons() {
    this.sendButton.classList.remove(this.classNames.loading);
    this.sendButton.parentElement.classList.remove(this.classNames.locked);
    this.sendButton.parentElement
      .querySelectorAll('.button')
      .forEach((button) => button.removeAttribute('disabled'));
  }

  /**
   * Display message after sending the code
   */
  displayCodeSentMessage() {
    if (!this.state.showCodeSentMessage) {
      return;
    }

    let container = this.codeFieldContainer;

    if (this.showEmailCodeSentMessage) {
      container = this.emailFieldContainer;
    }

    if (!container) {
      return;
    }

    this.codeSentMessage = container.querySelector(
      this.selectors.codeSentMessage,
    );

    if (!this.codeSentMessage) {
      this.codeSentMessage = document.createElement('p');
      this.codeSentMessage.classList.add(this.classNames.codeSentMessage);
      const icon = document.createElement('span');
      icon.classList.add('gform-icon', 'gform-icon--info');
      this.codeSentMessage.innerText = this.showEmailCodeSentMessage
        ? this.formInfo.settings['code-sent-message-email-field']
        : this.formInfo.settings['code-sent-message'];
      this.codeSentMessage.appendChild(icon);
      container.appendChild(this.codeSentMessage);
    }
  }

  /**
   * Ajax request helper function
   */
  ajaxRequest = async (params, action) => {
    const { ajaxUrl, nonce } = this.localizedData;
    const ajaxAction = `rfs_gf_email_verification_${action}`;

    const response = await fetch(`${ajaxUrl}`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Cache-Control': 'no-cache',
      },
      body: new URLSearchParams({
        nonce,
        action: ajaxAction,
        ...params,
      }),
    });

    const result = await response.json();

    return result;
  };
}

/**
 * Run the code on each page render of the form
 */
jQuery(document).on('gform_post_render', (event, formId, currentPage) => {
  // eslint-disable-next-line no-new
  new GFEmailVerification(formId, currentPage);
});
