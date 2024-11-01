<fieldset class="wc-credit-card-form wc-payment-form" id="wc-shuffle-cc-form">
    <p class="form-row form-row-wide woocommerce-validated">
        <label for="wc-shuffle-card-number">
            Card Number
            <span class="required">
                *
            </span>
        </label>
        <input autocapitalize="no" autocomplete="card_number" maxlength="16" autocorrect="no" class="input-text wc-credit-card-form-card-number" id="wc-shuffle-card-number" inputmode="numeric" name="card_number" placeholder="•••• •••• •••• ••••" spellcheck="no" type="tel"/>
    </p>
    <p class="form-row form-row-first woocommerce-validated">
        <label for="wc-shuffle-card-exp">
            Card Expiration (MM/YY)
            <span class="required">
                *
            </span>
        </label>
        <input autocapitalize="no" autocomplete="card_exp" autocorrect="no" class="input-text wc-credit-card-form-card-exp" id="wc-shuffle-card-exp" inputmode="numeric" name="card_exp" placeholder="MM / YY" spellcheck="no" type="tel" onkeyup="
		var date = this.value;
		if (date.match(/^\d{2}$/) !== null) {
		   this.value = date + '/';
		}" maxlength="5"/>
    </p>
    <p class="form-row form-row-last woocommerce-validated">
        <label for="wc-shuffle-card-cvc">
            Card CVV
            <span class="required">
                *
            </span>
        </label>
        <input autocapitalize="no" autocomplete="off" autocorrect="no" class="input-text wc-credit-card-form-card-cvv" id="wc-shuffle-card-cvv" inputmode="numeric" maxlength="4" name="card_cvv" placeholder="CVV" spellcheck="no" style="width:100px" type="tel"/>
    </p>
    <div class="clear"></div>
</fieldset>
