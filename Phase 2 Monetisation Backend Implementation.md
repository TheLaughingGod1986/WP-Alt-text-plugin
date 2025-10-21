You are building the monetisation backend for a WordPress plugin called “AltText AI.” 
The plugin already works locally and connects via HTTP to generate AI alt text. 
Your task is to implement the SaaS backend that manages user accounts, plans, usage quotas, and Stripe billing.

---

🎯 GOAL
Create a secure backend and API system that:
- Handles user registration, login, and token issuance
- Tracks tokens/usage per plan
- Integrates Stripe for recurring subscriptions + credit packs
- Communicates with the WordPress plugin via JSON endpoints
- Supports free (10 images/mo) and paid plans (£12.99 Pro, £49.99 Agency)
- Resets usage monthly via cron or Stripe webhooks

---

🧩 STACK REQUIREMENTS
- Node.js + Express (TypeScript optional)
- PostgreSQL via Prisma or Sequelize ORM
- JWT authentication
- Stripe Billing + Checkout + Customer Portal
- Hosting: Railway / Render / Vercel
- Environment variables:
  - `STRIPE_SECRET_KEY`
  - `OPENAI_API_KEY`
  - `JWT_SECRET`
  - `DATABASE_URL`

---

🧱 DATABASE SCHEMA (Prisma Example)

model User {
  id                Int      @id @default(autoincrement())
  email             String   @unique
  passwordHash      String
  plan              String   @default("free")
  tokensRemaining   Int      @default(10)
  credits           Int      @default(0)
  stripeCustomerId  String?
  createdAt         DateTime @default(now())
  updatedAt         DateTime @updatedAt
}

model UsageLog {
  id        Int      @id @default(autoincrement())
  userId    Int
  used      Int
  createdAt DateTime @default(now())
  User      User     @relation(fields: [userId], references: [id])
}

---

🧠 API ROUTES TO IMPLEMENT

| Method | Route | Description |
|--------|-------|-------------|
| POST | `/auth/register` | Create account; hash password; issue JWT |
| POST | `/auth/login` | Authenticate; return JWT |
| GET | `/usage` | Return tokens, credits, plan, reset date |
| POST | `/generate` | Validate JWT; check tokens; call OpenAI; decrement usage |
| POST | `/billing/checkout` | Create Stripe Checkout Session (plan or credits) |
| POST | `/billing/webhook` | Handle Stripe events (renewals, cancel, upgrade) |
| POST | `/credits/buy` | Handle one-off credit purchase |

---

🔐 AUTH & TOKEN HANDLING

1. On login/signup → issue JWT token.
2. Plugin stores JWT locally (`alttextai_token`).
3. Every `/generate` call includes header:
   `Authorization: Bearer <token>`.
4. Middleware verifies JWT → attaches `req.user`.

---

💳 STRIPE SETUP

Create 3 Products in Stripe:
- Free (Internal) – 10 images
- Pro – 100 images / £12.99 per month
- Agency – 1000 images / £49.99 per month

Create Price IDs for each subscription.

Create webhook endpoint `/billing/webhook` listening to:
- `checkout.session.completed` → activate plan
- `invoice.paid` → reset tokens
- `customer.subscription.deleted` → downgrade to Free

Enable Stripe Customer Portal for “Manage Billing” link.

---

🧮 TOKEN LOGIC (Middleware Snippet)
```js
if (user.plan === 'free' && user.tokensRemaining <= 0 && user.credits <= 0) {
  return res.status(403).json({ error: 'limit_reached' });
}

await openai.generateAltText();
user.tokensRemaining--;
await user.save();