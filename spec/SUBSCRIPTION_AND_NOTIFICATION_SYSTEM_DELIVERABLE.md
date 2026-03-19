# Subscription and Notification System - Deliverable Document

## Overview

A comprehensive subscription and notification system has been implemented for the school website, allowing parents to receive updates about their children's classes through both in-app notifications and web push notifications.

## System Architecture

The implementation consists of a custom Craft CMS module (`modules/notifications`) that provides:

1. **Magic Link Authentication** - Passwordless login system for parents
2. **Class Subscriptions** - Parents can subscribe to specific classes
3. **Push Notifications** - Web push notifications for real-time updates
4. **Admin Interface** - Teachers can send notifications directly from Craft CP

## Database Changes

### New Tables Created

The migration `m251210_000000_notifications.php` creates four new database tables:

1. **`user_class_subscriptions`** - Tracks which classes each parent follows
2. **`user_push_subscriptions`** - Stores web push subscription data for notifications
3. **`magic_link_tokens`** - Manages passwordless authentication tokens
4. **`notification_logs`** - Tracks when notifications are sent for rate limiting

### Key Indexes
- Unique constraints prevent duplicate subscriptions
- Foreign key relationships ensure data integrity
- Performance indexes on frequently queried fields

## Core Features Implemented

### 1. Magic Link Authentication System
- **Location**: `modules/notifications/src/controllers/AuthController.php`
- **Purpose**: Passwordless login via email links
- **Security**: 15-minute token expiration, single-use tokens, rate limiting

### 2. Class Subscription Management
- **Location**: `modules/notifications/src/services/Subscriptions.php`
- **Purpose**: Manage parent subscriptions to classes
- **Features**: Subscribe/unsubscribe, get subscribed classes

### 3. Push Notification System
- **Client**: `src/scripts/components/subscriptions.js`
- **Server**: `modules/notifications/src/services/Notifications.php`
- **Features**: Web Push API integration, service worker management

### 4. Admin Notification Interface
- **Location**: `templates/_admin/push-notification.twig`
- **Purpose**: Allow teachers to send notifications from Craft CP
- **Integration**: Automatically appears on class entry pages

## File Changes Summary

### Backend Changes
- **New Module**: Complete `modules/notifications/` structure
- **Migration**: Database schema in `migrations/m251210_000000_notifications.php`
- **Services**: Auth, Subscriptions, and Notifications services
- **Controllers**: Auth, Subscriptions, Notifications, and Dashboard controllers
- **Records**: Active Record classes for database tables

### Frontend Changes
- **Templates**: Login, subscriptions, and admin notification templates
- **JavaScript**: Push notification subscription component
- **Styles**: Subscription component styling
- **Service Worker**: `public/sw.js` for push notification handling

### Key Routes Added
- `/notifications/auth/send-magic-link` - Send magic link email
- `/notifications/auth/verify` - Verify magic link token
- `/notifications/subscriptions/subscribe-push` - Subscribe to push notifications
- `/notifications/subscriptions/unsubscribe-push` - Unsubscribe from push notifications
- `/notifications/subscriptions/save` - Save class subscriptions
- `/notifications/notifications/send` - Send notification (admin)

## Testing Procedures

### 1. Magic Link Authentication Testing

**Test Case**: New User Registration
1. Navigate to `/login`
2. Enter a new email address
3. Check email for magic link
4. Click magic link
5. **Expected**: User created and redirected to subscriptions page

**Test Case**: Existing User Login
1. Use email of existing user
2. Follow magic link
3. **Expected**: User logged in and redirected to dashboard

**Test Case**: Token Security
1. Try using expired token (>15 minutes)
2. Try using token twice
3. **Expected**: Both should show error and redirect to login

### 2. Subscription Management Testing

**Test Case**: Class Subscription
1. Login as parent user
2. Navigate to subscriptions page
3. Select classes and save
4. **Expected**: Classes saved to database, user redirected to appropriate page

**Test Case**: Subscription Persistence
1. Subscribe to classes
2. Logout and login again
3. **Expected**: Subscriptions still active

### 3. Push Notification Testing

**Test Case**: Browser Permission Flow
1. Navigate to subscriptions page
2. Click "Enable Notifications"
3. **Expected**: Browser permission prompt appears

**Test Case**: Push Subscription (Android/Desktop Chrome)
1. Grant notification permission
2. **Expected**: Service worker registers, push subscription created

**Test Case**: iOS Safari Flow
1. Visit on iOS Safari
2. **Expected**: Instructions to "Add to Home Screen" appear

**Test Case**: Notification Sending
1. Login as teacher/admin
2. Edit a class entry
3. Use "Publish Notification" button
4. **Expected**: Push notifications sent to subscribed parents

### 4. Admin Interface Testing

**Test Case**: Notification Button Visibility
1. Login as admin/teacher
2. Edit any class entry
3. **Expected**: "Publish Notification" button appears in CP

**Test Case**: Custom Notification Messages
1. Use admin notification interface
2. Send custom message
3. **Expected**: Custom message sent instead of default

### 5. Security Testing

**Test Case**: Authentication Required
1. Try accessing `/dashboard` without login
2. **Expected**: Redirected to login page

**Test Case**: CSRF Protection
1. Try submitting forms without CSRF token
2. **Expected**: Request rejected

**Test Case**: User Data Isolation
1. Login as different users
2. **Expected**: Each user only sees their own subscriptions

### 6. Error Handling Testing

**Test Case**: Invalid Tokens
1. Use malformed magic link token
2. **Expected**: Error message, redirect to login

**Test Case**: Expired Service Worker
1. Manually invalidate push subscription
2. **Expected**: Graceful failure, subscription cleaned up

**Test Case**: Network Failures
1. Test with poor network conditions
2. **Expected**: Appropriate error messages shown

## Performance Considerations

### Database Optimization
- Indexes on frequently queried columns
- Foreign key relationships for data integrity
- Proper cleanup of expired tokens

### Frontend Performance
- Service worker registration only when needed
- Minimal JavaScript payload
- Progressive enhancement for push notifications

### Security Measures
- CSRF protection on all forms
- Rate limiting on magic link requests
- Secure token generation using `random_bytes()`
- Automatic cleanup of expired tokens

## Browser Support

### Push Notifications Support
- **✅ Supported**: Chrome (Android/Desktop), Firefox, Safari (macOS)
- **⚠️ Partial**: Safari iOS (requires PWA installation)
- **❌ Not Supported**: Internet Explorer

### Graceful Degradation
- Unsupported browsers show appropriate messages
- System works without push notifications enabled
- Progressive enhancement approach

## Configuration Requirements

### Environment Variables
- `VAPID_PUBLIC_KEY` - For push notification authentication
- `VAPID_PRIVATE_KEY` - For push notification authentication
- Email service configuration (Postmark/etc.)

### Craft CMS Setup
- User groups configured for parents, teachers, admins
- Proper field layouts for class entries
- Email templates for magic links

## Future Enhancements

Based on the specification document, potential Phase 2 improvements include:
- Email digest notifications
- SMS notifications for urgent updates
- Enhanced notification preferences
- Parent-teacher messaging
- Event RSVP tracking

## Rollback Procedures

If rollback is needed:
1. Disable module in `config/app.php`
2. Remove module routes
3. Drop database tables using migration `safeDown()`
4. Remove frontend JavaScript components
5. Clean up templates and admin interfaces

## Support and Maintenance

### Regular Maintenance Tasks
- Monitor notification delivery rates
- Clean up expired magic link tokens
- Review push subscription validity
- Update VAPID keys as needed

### Monitoring Points
- Magic link delivery success rates
- Push notification delivery rates
- User subscription patterns
- Database table growth

This system provides a robust foundation for parent-school communication while maintaining security, performance, and user experience standards.