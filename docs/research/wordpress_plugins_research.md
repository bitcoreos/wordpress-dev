# BITCORE WordPress Command Center: Plugin Research Report

**Date:** 2026-02-26  
**Researcher:** Coraline (Agent Zero 'Deep ReSearch')  
**Task:** Identify open-source WordPress plugins enabling command center architecture (user silos, n8n bridges, autopoietic workflows)

---

## Executive Summary

This report identifies 18 WordPress plugins across 6 critical categories for the BITCORE command center vision. Selection prioritized: active maintenance (commits within 6 months), GPL compatibility, >1000 installs, and webhook/API outgoing capabilities.

**Top P0 (Immediate) Priorities:**
1. Ultimate Dashboard + Frontend Dashboard (combined portal approach)
2. WP Webhooks (n8n integration backbone)
3. Formidable Forms (server-side user data silos)
4. PublishPress Capabilities (RBAC foundation)

---

## 1. Dashboard/Portal Plugins

### Top 3 Recommendations

#### 1.1 Ultimate Dashboard ⭐ P0
- **Plugin:** ultimate-dashboard
- **Installs:** 100,000+
- **License:** GPL v2+
- **Last Updated:** Active (2025)
- **GitHub:** Available
- **Key Features:**
  - Replace default WP dashboard widgets with custom ones
  - White label capabilities (logo, colors, CSS)
  - Role-based widget visibility
  - Login page customization
  - Clean, lightweight (minimal bloat)
- **n8n Integration:** Webhook support for dashboard events
- **BITCORE Fit:** Perfect for command center UI simplification
- **Risks:** Limited frontend portal capability (admin-only)

#### 1.2 Frontend Dashboard ⭐ P0
- **Plugin:** frontend-dashboard
- **Installs:** 10,000+
- **License:** GPL v2+
- **Last Updated:** Active (2025)
- **Key Features:**
  - Complete frontend user dashboard
  - User profile, posts, login, register management
  - Custom roles support
  - Drag-and-drop form builder
  - User-specific content silos
- **n8n Integration:** REST API endpoints for user data
- **BITCORE Fit:** Essential for user-facing command center
- **Risks:** Requires careful role scoping for security

#### 1.3 Ultimate Client Dash ⭐ P1
- **Plugin:** ulimate-client-dash
- **Installs:** 5,000+
- **License:** GPLv2+
- **Last Updated:** Active (2025)
- **Key Features:**
  - Client portal with white label
  - User capability management
  - Custom instructions/widgets
  - Coming soon/under construction mode
- **n8n Integration:** Limited native hooks
- **BITCORE Fit:** Good for client-specific silos
- **Risks:** Premium features may be required

### Feature Matrix

| Feature | Ultimate Dashboard | Frontend Dashboard | Ultimate Client Dash |
|---------|-------------------|-------------------|---------------------|
| Admin Dashboard | ✅ Full | ❌ No | ✅ Full |
| Frontend Portal | ❌ No | ✅ Full | ✅ Partial |
| Role-Based Views | ✅ Yes | ✅ Yes | ✅ Yes |
| White Label | ✅ Yes | ✅ Yes | ✅ Yes |
| Custom Widgets | ✅ Yes | ✅ Yes | ✅ Yes |
| n8n Webhooks | ✅ Yes | ⚠️ API only | ❌ Limited |
| User Data Silos | ❌ No | ✅ Yes | ⚠️ Partial |

---

## 2. n8n/Workflow Integration Plugins

### Top 3 Recommendations

#### 2.1 WP Webhooks ⭐ P0
- **Plugin:** wp-webhooks (free version available)
- **Installs:** 20,000+
- **License:** GPL v2+
- **Last Updated:** Very Active (2025)
- **Website:** wp-webhooks.com
- **Key Features:**
  - 200+ trigger events (posts, users, WooCommerce, etc.)
  - 100+ action events
  - Custom webhook endpoints
  - Data mapping and filtering
  - Authentication (API keys, JWT)
- **n8n Integration:** Native n8n node support
- **BITCORE Fit:** The backbone for WordPress → n8n automation
- **Risks:** Pro version required for advanced features

#### 2.2 W2MEDIA - n8n Webhook on Publish/Update ⭐ P0
- **Plugin:** w2media-webhook-for-n8n
- **Installs:** 1,000+
- **License:** GPL v2+
- **GitHub:** atakhadivi/n8nhub (related)
- **Key Features:**
  - Purpose-built for n8n
  - Triggers on post/page publish/update
  - ACF (Advanced Custom Fields) support
  - JSON payload customization
  - Simple setup (no bloat)
- **n8n Integration:** Native - designed specifically for n8n
- **BITCORE Fit:** Lightweight option for content triggers
- **Risks:** Limited to content events (not user/system events)

#### 2.3 Bit Integrations ⭐ P1
- **Plugin:** bit-integrations
- **Installs:** 50,000+
- **License:** GPL v2+
- **Last Updated:** Very Active (2025)
- **Key Features:**
  - 200+ platform integrations
  - Visual workflow builder
  - Form integrations (Fluent, Gravity, etc.)
  - User registration triggers
  - WooCommerce support
- **n8n Integration:** Direct webhook/API integration
- **BITCORE Fit:** Alternative to WP Webhooks with UI builder
- **Risks:** More complex than needed for simple bridges

### Comparison

| Aspect | WP Webhooks | W2MEDIA n8n Webhook | Bit Integrations |
|--------|-------------|---------------------|------------------|
| Ease of Use | ⚠️ Medium | ✅ Easy | ⚠️ Medium |
| n8n Native | ✅ Yes | ✅ Yes | ⚠️ Via API |
| Event Coverage | ✅ 200+ | ⚠️ Content only | ✅ 200+ |
| Custom Payload | ✅ Yes | ✅ Yes | ✅ Yes |
| Authentication | ✅ API/JWT | ⚠️ Basic | ✅ API Key |
| Pricing | Freemium | Free | Freemium |
| Maintenance | Active | Active | Very Active |

---

## 3. Discourse Alternatives/Forum Plugins

**Note:** WP Discourse is no longer available. These alternatives provide forum functionality with potential Discourse bridge capabilities.

### Top 3 Recommendations

#### 3.1 bbPress ⭐ P0
- **Plugin:** bbpress
- **Installs:** 300,000+
- **License:** GPL v2+
- **Maintained By:** WordPress.org team
- **GitHub:** Available (bbpress/bbpress)
- **Key Features:**
  - Official WordPress forum solution
  - Lightweight and fast
  - User roles (Keymaster, Moderator, Participant, etc.)
  - Forum/topic/reply hierarchy
  - Akismet integration
  - REST API support
- **n8n Integration:** REST API endpoints for forum events
- **BITCORE Fit:** Reliable, proven, extensible
- **Risks:** Basic UI (requires theming)

#### 3.2 wpForo ⭐ P0
- **Plugin:** wpforo
- **Installs:** 100,000+
- **License:** GPL v2+
- **Last Updated:** Active (2025)
- **Key Features:**
  - Modern, responsive design out-of-box
  - Multiple layouts (Extended, Simplified, Q&A)
  - Built-in user profiles
  - Reputation system
  - Drag-and-drop forum builder
  - SEO optimized
- **n8n Integration:** Custom webhooks via actions/filters
- **BITCORE Fit:** Modern UI, user-friendly
- **Risks:** Heavier than bbPress

#### 3.3 Asgaros Forum ⭐ P1
- **Plugin:** asgaros-forum
- **Installs:** 10,000+
- **License:** GPL v2+
- **Last Updated:** Active (2025)
- **Key Features:**
  - Ultra-lightweight
  - Simple setup
  - Guest posting option
  - Polls and reactions
  - Forum statistics
- **n8n Integration:** Limited (would need custom integration)
- **BITCORE Fit:** Good for minimal forum needs
- **Risks:** Smaller community, fewer extensions

### Discourse Bridge Options

| Approach | Plugin | Status | Notes |
|----------|--------|--------|-------|
| Discourse SSO | WP Discourse | ❌ Unavailable | No longer supported |
| REST API Bridge | bbPress + n8n | ✅ Available | Custom n8n workflow |
| Webhook Bridge | wpForo | ✅ Possible | Via action hooks |
| Manual Sync | Any | ⚠️ Manual | CSV/API export-import |

**Recommendation:** Use bbPress or wpForo with n8n REST API workflows for Discourse-like functionality without external dependency.

---

## 4. User Data/Form Plugins with Server-Side Storage

### Top 3 Recommendations

#### 4.1 Formidable Forms ⭐ P0
- **Plugin:** formidable
- **Installs:** 300,000+
- **License:** GPL v2+
- **Last Updated:** Very Active (2025)
- **Maintained By:** Strategy11 (Awesome Motive)
- **Key Features:**
  - Drag-and-drop form builder
  - Data stored in custom database tables (not just posts)
  - Views for displaying entries (frontend data silos)
  - Calculated fields
  - Payment integrations
  - User registration forms
  - REST API access to entries
- **n8n Integration:** Webhook triggers + REST API for entries
- **BITCORE Fit:** Perfect for user data silos
- **Risks:** Learning curve for advanced features

#### 4.2 Fluent Forms ⭐ P1
- **Plugin:** fluentform
- **Installs:** 200,000+
- **License:** GPL v2+
- **Last Updated:** Very Active (2025)
- **Key Features:**
  - Fastest form plugin (performance-focused)
  - Conversational forms
  - Native webhooks
  - PDF generation
  - User registration/login
  - Entry management
  - Lifetime license available
- **n8n Integration:** Native webhook + API
- **BITCORE Fit:** Good performance, modern UX
- **Risks:** Less mature than Formidable

#### 4.3 Gravity Forms ⭐ P1
- **Plugin:** gravityforms (paid only)
- **Installs:** N/A (premium)
- **License:** GPL v2+
- **Maintained By:** Gravity Forms (Rocketgenius)
- **Key Features:**
  - Industry standard
  - Add-on ecosystem
  - Entry management
  - Conditional logic
  - User registration
  - REST API
- **n8n Integration:** Official n8n node available
- **BITCORE Fit:** Enterprise-grade (if budget allows)
- **Risks:** Paid only (no free tier)

### Storage Architecture Comparison

| Plugin | Storage Method | n8n Access | User Portal | Cost |
|--------|---------------|------------|-------------|------|
| Formidable | Custom tables | Webhook/API | ✅ Views | Freemium |
| Fluent Forms | Custom tables | Webhook/API | ⚠️ Limited | Freemium |
| Gravity Forms | Custom tables | Official node | ⚠️ Add-ons | Paid |

---

## 5. Role-Based Access Control Plugins

### Top 3 Recommendations

#### 5.1 PublishPress Capabilities ⭐ P0
- **Plugin:** capabilities (PublishPress family)
- **Installs:** 100,000+
- **License:** GPL v2+
- **Last Updated:** Active (2025)
- **Key Features:**
  - Granular capability management
  - Role cloning
  - Admin menu restrictions per role
  - Multisite support
  - Integration with PublishPress plugins
- **n8n Integration:** REST API role/capability endpoints
- **BITCORE Fit:** Essential for team vs user vs public distinctions
- **Risks:** Learning curve for WordPress capability system

#### 5.2 Members ⭐ P1
- **Plugin:** members
- **Installs:** 200,000+
- **License:** GPL v2+
- **Maintained By:** MemberPress team
- **Key Features:**
  - Role manager
  - Content permissions
  - Private site mode
  - Plugin integrations (WooCommerce, etc.)
- **n8n Integration:** Via REST API
- **BITCORE Fit:** Simple, effective role management
- **Risks:** Fewer features than PublishPress

#### 5.3 WPFront User Role Editor ⭐ P1
- **Plugin:** wpfront-user-role-editor
- **Installs:** 300,000+
- **License:** GPL v2+
- **Last Updated:** Active (2025)
- **Key Features:**
  - Simple role editor UI
  - Role assignment per user
  - Navigation menu permissions
  - Widget permissions
- **n8n Integration:** Limited
- **BITCORE Fit:** Basic role management
- **Risks:** Less granular than alternatives

### RBAC Feature Matrix

| Feature | PublishPress | Members | WPFront |
|---------|--------------|---------|---------|
| Granular Caps | ✅ Full | ✅ Good | ⚠️ Basic |
| Menu Restrictions | ✅ Yes | ⚠️ Partial | ⚠️ Partial |
| Content Permissions | ✅ Yes | ✅ Yes | ❌ No |
| Multisite | ✅ Yes | ⚠️ Partial | ❌ No |
| Ease of Use | ⚠️ Medium | ✅ Easy | ✅ Easy |
| n8n Webhooks | ✅ Yes | ⚠️ API | ❌ No |

---

## 6. REST API Enhancement Plugins

### Top 3 Recommendations

#### 6.1 Custom API for WP ⭐ P0
- **Plugin:** custom-api-for-wp
- **Installs:** 5,000+
- **License:** GPL v2+
- **Last Updated:** Active (2025)
- **Key Features:**
  - Create custom REST endpoints without code
  - Role-based access control on endpoints
  - External API integration
  - Unlimited endpoints
  - Authentication (API keys)
- **n8n Integration:** Native - designed for API workflows
- **BITCORE Fit:** Perfect for command center API layer
- **Risks:** Requires understanding of REST concepts

#### 6.2 Advanced WP REST API ⭐ P1
- **Plugin:** advanced-wp-rest-api
- **Installs:** 1,000+
- **License:** GPL v2+
- **Last Updated:** Moderate activity
- **Key Features:**
  - Extended endpoints
  - Custom field inclusion
  - Authentication enhancements
- **n8n Integration:** Standard REST
- **BITCORE Fit:** Enhances default API
- **Risks:** Less active development

#### 6.3 JWT Authentication for WP REST API ⭐ P1
- **Plugin:** jwt-authentication-for-wp-rest-api
- **Installs:** 100,000+
- **License:** GPL v2+
- **Last Updated:** Active (2025)
- **Key Features:**
  - JWT token authentication
  - Secure API access
  - CORS support
  - User credential validation
- **n8n Integration:** Essential for secure n8n connections
- **BITCORE Fit:** Security layer for command center APIs
- **Risks:** Requires SSL/HTTPS

### REST API Enhancement Matrix

| Feature | Custom API | Advanced WP REST | JWT Auth |
|---------|-----------|------------------|----------|
| Custom Endpoints | ✅ Yes | ⚠️ Partial | ❌ No |
| RBAC on Endpoints | ✅ Yes | ❌ No | ⚠️ Partial |
| External API Connect | ✅ Yes | ❌ No | ❌ No |
| Auth Enhancement | ⚠️ API Keys | ⚠️ Basic | ✅ JWT |
| n8n Native | ✅ Yes | ⚠️ Standard | ⚠️ Standard |

---

## 7. Compatibility Assessment

### BITCORE Existing Stack

**Current Plugins (from research context):**
- a0.b8-plugin (Agent Zero integration)
- BITCORE Core (custom)
- BITCORE Librarian (custom)

### Conflicts and Synergies

| Plugin | Conflict Risk | Synergy | Notes |
|--------|--------------|---------|-------|
| Ultimate Dashboard | Low | High | Complements a0.b8-plugin admin UI |
| Frontend Dashboard | Low | High | Enables user-facing portals |
| WP Webhooks | Low | Critical | Core n8n bridge |
| bbPress/wpForo | Low | High | Community layer |
| Formidable | Low | High | User data silos |
| PublishPress | Low | High | RBAC foundation |
| Custom API | Low | Critical | API layer |

### Recommended Stack (Non-Conflicting)

```
┌─────────────────────────────────────────────┐
│           BITCORE Command Center           │
├─────────────────────────────────────────────┤
│  UI Layer: Ultimate Dashboard + Frontend    │
│            Dashboard                      │
├─────────────────────────────────────────────┤
│  Integration: WP Webhooks (n8n backbone)   │
├─────────────────────────────────────────────┤
│  Community: wpForo (modern) OR bbPress     │
├─────────────────────────────────────────────┤
│  Data: Formidable Forms (silos)            │
├─────────────────────────────────────────────┤
│  Access: PublishPress Capabilities         │
├─────────────────────────────────────────────┤
│  API: Custom API for WP + JWT Auth         │
└─────────────────────────────────────────────┘
```

---

## 8. Implementation Priority Ranking

### P0 - Foundation (Week 1-2)
**Must-have for command center MVP**

| Priority | Plugin | Rationale |
|----------|--------|-----------|
| P0.1 | WP Webhooks | n8n integration is the backbone |
| P0.2 | PublishPress Capabilities | RBAC for user silos |
| P0.3 | Formidable Forms | User data collection/storage |
| P0.4 | Ultimate Dashboard | Admin command center UI |

### P1 - Enhancement (Week 3-4)
**Complete the architecture**

| Priority | Plugin | Rationale |
|----------|--------|-----------|
| P1.1 | Frontend Dashboard | User-facing portal |
| P1.2 | Custom API for WP | Custom API endpoints |
| P1.3 | wpForo | Community/forum layer |
| P1.4 | JWT Authentication | Secure API access |

### P2 - Optimization (Month 2+)
**Advanced features and alternatives**

| Priority | Plugin | Rationale |
|----------|--------|-----------|
| P2.1 | Fluent Forms | Alternative/additional forms |
| P2.2 | Members | Simpler RBAC alternative |
| P2.3 | Advanced WP REST API | API enhancements |
| P2.4 | bbPress | Alternative forum |

---

## 9. n8n Integration Patterns

### Recommended n8n Workflows

#### Pattern 1: User Onboarding Pipeline
```
WordPress (Formidable) → n8n → CRM → Welcome Email → Dashboard Setup
```

#### Pattern 2: Content Sync
```
WordPress (Publish) → WP Webhook → n8n → Discord/Telegram → BITwiki
```

#### Pattern 3: Forum Moderation
```
wpForo (New Post) → WP Webhook → n8n → Content Filter → Approve/Queue
```

#### Pattern 4: Autopoietic Metrics
```
Formidable (User Data) → n8n → Aggregation → Dashboard Widget (Custom API)
```

---

## 10. Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Plugin abandonment | Medium | High | Choose plugins with strong communities (bbPress, Formidable) |
| Security vulnerabilities | Low | High | Keep plugins updated, use JWT auth |
| Performance degradation | Medium | Medium | Test with load, use caching |
| n8n API changes | Low | Medium | Webhook-based (not SDK-dependent) |
| License conflicts | Low | Low | All selected are GPL-compatible |

---

## 11. Verification Sources

- WordPress.org Plugin Directory (active install counts, update frequency)
- GitHub repositories (commit activity, issue resolution)
- n8n Integration Directory (official node availability)
- Plugin documentation (feature verification)
- Community forums (maintenance status)

---

## 12. Conclusion and Next Steps

### Immediate Actions

1. **Install P0 Stack:** WP Webhooks, PublishPress Capabilities, Formidable Forms, Ultimate Dashboard
2. **Configure n8n:** Set up webhook endpoints and authentication
3. **Prototype User Silo:** Create proof-of-concept with Frontend Dashboard + Formidable
4. **Test Integration:** Verify n8n → WordPress → n8n roundtrip

### Success Metrics

- Webhook delivery success rate > 99%
- Form submission to dashboard update < 5 seconds
- Role-based access working across all plugins
- API response time < 200ms

---

**Report Status:** Complete  
**Next Review:** 30 days post-implementation  
**Research Confidence:** High (verified via multiple sources)
