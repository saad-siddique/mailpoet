import React from 'react';
import { SaveButton } from 'settings/components';
import EmailCustomizer from './email_customizer';
import CheckoutOptin from './checkout_optin';
import SubscribeOldCustomers from './subscribe_old_customers';

export default function WooCommerce() {
  return (
    <div className="mailpoet-settings-grid">
      <EmailCustomizer />
      <CheckoutOptin />
      <SubscribeOldCustomers />
      <SaveButton />
    </div>
  );
}
