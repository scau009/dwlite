import { useState } from 'react';
import { Link } from 'react-router';
import { useTranslation } from 'react-i18next';
import { ProForm, ProFormText } from '@ant-design/pro-components';
import { Card, Result, App } from 'antd';
import { MailOutlined } from '@ant-design/icons';

import { authApi } from '@/lib/auth-api';
import { validateEmail } from '@/lib/validation';

export function ForgotPasswordPage() {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [success, setSuccess] = useState(false);

  const handleSubmit = async (values: { email: string }) => {
    const emailError = validateEmail(values.email);
    if (emailError) {
      message.error(emailError);
      return false;
    }

    try {
      await authApi.forgotPassword({ email: values.email });
      setSuccess(true);
    } catch {
      // Always show success to prevent email enumeration
      setSuccess(true);
    }
    return true;
  };

  if (success) {
    return (
      <Card>
        <Result
          status="success"
          title={t('auth.checkYourEmail')}
          subTitle={t('auth.resetLinkSent')}
          extra={
            <Link to="/login" className="text-blue-500 hover:underline">
              {t('auth.backToLogin')}
            </Link>
          }
        />
      </Card>
    );
  }

  return (
    <Card>
      <div className="text-center mb-6">
        <h2 className="text-2xl font-semibold">{t('auth.forgotPassword')}</h2>
        <p className="text-gray-500">
          {t('auth.forgotPasswordSubtitle')}
        </p>
      </div>

      <ProForm
        layout="vertical"
        onFinish={handleSubmit}
        submitter={{
          searchConfig: {
            submitText: t('auth.sendResetLink'),
          },
          resetButtonProps: { style: { display: 'none' } },
          submitButtonProps: { block: true, size: 'large' },
        }}
      >
        <ProFormText
          name="email"
          label={t('auth.email')}
          fieldProps={{
            size: 'large',
            prefix: <MailOutlined />,
          }}
          placeholder={t('auth.emailPlaceholder')}
          rules={[
            { required: true, message: t('auth.emailRequired') },
            { type: 'email', message: t('auth.emailInvalid') },
          ]}
        />
      </ProForm>

      <div className="text-center mt-4">
        <Link to="/login" className="text-blue-500 hover:underline">
          {t('auth.backToLogin')}
        </Link>
      </div>
    </Card>
  );
}
