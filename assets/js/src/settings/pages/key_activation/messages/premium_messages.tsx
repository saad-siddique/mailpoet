import React from 'react';
import MailPoet from 'mailpoet';
import { useSelector } from 'settings/store/hooks/index';
import { PremiumInstallationStatus } from 'settings/store/types';
import PremiumInstallationMessages from './premium_installation_messages';

const ActiveMessage = () => (
  <div className="mailpoet_success">
    {MailPoet.I18n.t('premiumTabPremiumActiveMessage')}
  </div>
);

const InstallingMessage = () => (
  <div className="mailpoet_success">
    {MailPoet.I18n.t('premiumTabPremiumInstallingMessage')}
  </div>
);

const ActivatingMessage = () => (
  <div className="mailpoet_success">
    {MailPoet.I18n.t('premiumTabPremiumActivatingMessage')}
  </div>
);

type PremiumNotInstalledMessageProps = { callback: () => any }
const PremiumNotInstalledMessage = ({ callback }: PremiumNotInstalledMessageProps) => (
  <div className="mailpoet_error">
    {MailPoet.I18n.t('premiumTabPremiumNotInstalledMessage')}
    {' '}
    <button type="button" className="button-link" onClick={callback}>
      {MailPoet.I18n.t('premiumTabPremiumInstallMessage')}
    </button>
  </div>
);

type PremiumNotActiveMessageProps = { callback: () => any }
const PremiumNotActiveMessage = ({ callback }: PremiumNotActiveMessageProps) => (
  <div className="mailpoet_error">
    {MailPoet.I18n.t('premiumTabPremiumNotActiveMessage')}
    {' '}
    <button type="button" className="button-link" onClick={callback}>
      {MailPoet.I18n.t('premiumTabPremiumActivateMessage')}
    </button>
  </div>
);

type NotValidMessageProps = { message?: string }
const NotValidMessage = ({ message }: NotValidMessageProps) => (
  <div className="mailpoet_error">
    {message || MailPoet.I18n.t('premiumTabPremiumKeyNotValidMessage')}
  </div>
);

type Props = {
  keyMessage?: string
  activationCallback: () => any
  installationCallback: () => any
  installationStatus: PremiumInstallationStatus
}
export default function PremiumMessages(props: Props) {
  const { premiumStatus: status } = useSelector('getKeyActivationState')();
  return (
    <>
      {status === 'valid_premium_plugin_active' && <ActiveMessage />}
      {status === 'valid_premium_plugin_not_active' && (
        <PremiumNotActiveMessage callback={props.activationCallback} />
      )}
      {status === 'valid_premium_plugin_not_installed' && (
        <PremiumNotInstalledMessage callback={props.installationCallback} />
      )}
      {status === 'valid_premium_plugin_being_installed' && <InstallingMessage />}
      {status === 'valid_premium_plugin_being_activated' && <ActivatingMessage />}
      {status === 'invalid' && <NotValidMessage message={props.keyMessage} />}
      <PremiumInstallationMessages installationStatus={props.installationStatus} />
    </>
  );
}
