import { useEffect, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import { useTranslation } from 'react-i18next';
import { Card, Result, Spin } from 'antd';
import { LoadingOutlined } from '@ant-design/icons';

import { authApi } from '@/lib/auth-api';

type VerifyStatus = 'loading' | 'success' | 'error';

export function VerifyEmailPage() {
  const { t } = useTranslation();
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') || '';

  const [status, setStatus] = useState<VerifyStatus>(() =>
    !token ? 'error' : 'loading'
  );
  const [message, setMessage] = useState(() =>
    !token ? 'Invalid verification link. No token provided.' : ''
  );
  const verifyAttempted = useRef(false);

  useEffect(() => {
    if (!token) {
      return;
    }

    // Prevent duplicate requests in React 18 StrictMode
    if (verifyAttempted.current) {
      return;
    }
    verifyAttempted.current = true;

    const verifyEmail = async () => {
      try {
        const result = await authApi.verifyEmail(token);
        setStatus('success');
        setMessage(result.message);
      } catch (error) {
        setStatus('error');
        const apiErr = error as { error?: string };
        setMessage(
          apiErr.error || 'Email verification failed. The link may have expired.'
        );
      }
    };

    verifyEmail();
  }, [token]);

  return (
    <Card>
      {status === 'loading' && (
        <div className="text-center py-8">
          <Spin indicator={<LoadingOutlined style={{ fontSize: 48 }} spin />} />
          <p className="mt-4 text-lg">{t('auth.verifyEmail')}...</p>
        </div>
      )}

      {status === 'success' && (
        <Result
          status="success"
          title="Email verified!"
          subTitle={message}
          extra={
            <Link to="/login" className="text-blue-500 hover:underline">
              Go to login
            </Link>
          }
        />
      )}

      {status === 'error' && (
        <Result
          status="error"
          title="Verification failed"
          subTitle={message}
          extra={
            <Link to="/login" className="text-blue-500 hover:underline">
              Go to login
            </Link>
          }
        />
      )}
    </Card>
  );
}
