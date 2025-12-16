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
          title="Check your email"
          subTitle="If an account with that email exists, we've sent you a password reset link. Please check your inbox and spam folder."
          extra={
            <Link to="/login" className="text-blue-500 hover:underline">
              Back to login
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
          Enter your email address and we'll send you a reset link
        </p>
      </div>

      <ProForm
        layout="vertical"
        onFinish={handleSubmit}
        submitter={{
          searchConfig: {
            submitText: 'Send reset link',
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
          placeholder="you@example.com"
          rules={[
            { required: true, message: 'Please enter your email' },
            { type: 'email', message: 'Please enter a valid email' },
          ]}
        />
      </ProForm>

      <div className="text-center mt-4">
        <Link to="/login" className="text-blue-500 hover:underline">
          Back to login
        </Link>
      </div>
    </Card>
  );
}
