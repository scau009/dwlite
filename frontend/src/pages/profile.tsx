import { useState, type FormEvent } from 'react';
import { useAuth } from '@/contexts/auth-context';
import { authApi } from '@/lib/auth-api';
import { validateChangePasswordForm } from '@/lib/validation';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import type { ApiError } from '@/types/auth';

export function ProfilePage() {
  const { user } = useAuth();

  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [apiError, setApiError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleChangePassword = async (e: FormEvent) => {
    e.preventDefault();
    setApiError(null);
    setSuccess(false);

    const validation = validateChangePasswordForm(
      currentPassword,
      newPassword,
      confirmPassword
    );
    if (!validation.isValid) {
      setErrors(validation.errors);
      return;
    }
    setErrors({});

    setIsSubmitting(true);
    try {
      await authApi.changePassword({ currentPassword, newPassword });
      setSuccess(true);
      setCurrentPassword('');
      setNewPassword('');
      setConfirmPassword('');
    } catch (error) {
      const apiErr = error as ApiError;
      if (apiErr.violations) {
        setErrors(apiErr.violations);
      } else {
        setApiError(
          apiErr.error || 'Failed to change password. Please try again.'
        );
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="space-y-6">
      <h1 className="text-3xl font-bold text-gray-900 dark:text-white">
        Profile
      </h1>

      <div className="grid gap-6 md:grid-cols-2">
        {/* User Info Card */}
        <Card>
          <CardHeader>
            <CardTitle>Account Information</CardTitle>
            <CardDescription>Your account details</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div>
              <Label className="text-sm text-gray-500">User ID</Label>
              <p className="font-mono text-sm">{user?.id}</p>
            </div>
            <div>
              <Label className="text-sm text-gray-500">Email</Label>
              <p>{user?.email}</p>
            </div>
            <div>
              <Label className="text-sm text-gray-500">Email Verified</Label>
              <p>{user?.isVerified ? 'Yes' : 'No'}</p>
            </div>
            <div>
              <Label className="text-sm text-gray-500">Account Created</Label>
              <p>
                {user?.createdAt && new Date(user.createdAt).toLocaleString()}
              </p>
            </div>
            <div>
              <Label className="text-sm text-gray-500">Roles</Label>
              <p>{user?.roles.join(', ')}</p>
            </div>
          </CardContent>
        </Card>

        {/* Change Password Card */}
        <Card>
          <CardHeader>
            <CardTitle>Change Password</CardTitle>
            <CardDescription>Update your password</CardDescription>
          </CardHeader>
          <form onSubmit={handleChangePassword}>
            <CardContent className="space-y-4">
              {success && (
                <Alert>
                  <AlertDescription>
                    Password changed successfully!
                  </AlertDescription>
                </Alert>
              )}
              {apiError && (
                <Alert variant="destructive">
                  <AlertDescription>{apiError}</AlertDescription>
                </Alert>
              )}
              <div className="space-y-2">
                <Label htmlFor="currentPassword">Current Password</Label>
                <Input
                  id="currentPassword"
                  type="password"
                  value={currentPassword}
                  onChange={(e) => setCurrentPassword(e.target.value)}
                  disabled={isSubmitting}
                />
                {errors.currentPassword && (
                  <p className="text-sm text-red-500">{errors.currentPassword}</p>
                )}
              </div>
              <div className="space-y-2">
                <Label htmlFor="newPassword">New Password</Label>
                <Input
                  id="newPassword"
                  type="password"
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  disabled={isSubmitting}
                />
                {errors.newPassword && (
                  <p className="text-sm text-red-500">{errors.newPassword}</p>
                )}
                <p className="text-xs text-gray-500">
                  Min 8 characters with uppercase, lowercase, and number
                </p>
              </div>
              <div className="space-y-2">
                <Label htmlFor="confirmPassword">Confirm New Password</Label>
                <Input
                  id="confirmPassword"
                  type="password"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  disabled={isSubmitting}
                />
                {errors.confirmPassword && (
                  <p className="text-sm text-red-500">{errors.confirmPassword}</p>
                )}
              </div>
            </CardContent>
            <CardFooter>
              <Button type="submit" disabled={isSubmitting}>
                {isSubmitting ? 'Changing...' : 'Change Password'}
              </Button>
            </CardFooter>
          </form>
        </Card>
      </div>
    </div>
  );
}
