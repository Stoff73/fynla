<template>
  <PublicLayout>
    <!-- Hero Header -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Terms of
          <span class="text-raspberry-300">Service</span>
        </h1>
        <p class="text-lg text-white/70">
          The terms and conditions governing your use of the Fynla financial planning application.
        </p>
      </div>
    </div>

    <div class="bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">

        <p class="text-sm text-horizon-400 mb-10 pb-8 border-b border-light-gray">Last updated: 20 February 2026 &middot; Version 1.0</p>

        <!-- Introduction -->
        <div class="mb-10">
          <p class="text-neutral-500 leading-relaxed mb-4">These terms of service ("Terms") govern your use of the Fynla financial planning application available at <a href="https://fynla.org" class="text-violet-600 hover:text-violet-800 underline">fynla.org</a> (the "Service"). By creating an account or using the Service, you agree to be bound by these Terms and our <router-link to="/privacy" class="text-violet-600 hover:text-violet-800 underline">Privacy Policy</router-link>.</p>
          <p class="text-neutral-500 leading-relaxed">Please read these Terms carefully before using the Service. If you do not agree to these Terms, you must not use the Service. We recommend that you save or print a copy of these Terms for your records.</p>
        </div>

        <!-- 1. About Us -->
        <section class="mb-10" id="about-us">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">1. About Us</h2>
          <p class="text-neutral-500 leading-relaxed mb-4">The Service is operated by:</p>
          <div class="pl-4 border-l-2 border-light-gray mb-4">
            <p class="text-horizon-500 font-semibold">Fynla Limited</p>
            <p class="text-neutral-500">124 City Road, London, EC1V 2NX</p>
            <p class="text-neutral-500">Company Number: 16903721</p>
          </div>
          <p class="text-neutral-500 leading-relaxed">
            Email: <a href="mailto:support@fynla.org" class="text-violet-600 hover:text-violet-800 underline">support@fynla.org</a>
          </p>
        </section>

        <!-- 2. The Service -->
        <section class="mb-10" id="the-service">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">2. The Service</h2>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">What Fynla Does</h3>
          <p class="text-neutral-500 leading-relaxed mb-5">Fynla is a digital financial planning tool designed for individuals and couples in the United Kingdom. The Service helps you organise, visualise, and plan your finances across seven modules: Protection, Savings, Investments, Retirement, Estate Planning, Goals and Life Events, and Coordination. The Service provides financial planning tools, calculations, projections, and educational information based on your inputs and publicly available UK tax rates, actuarial data, and standard financial modelling techniques.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">What Fynla Does Not Do</h3>
          <p class="text-neutral-500 leading-relaxed mb-4"><strong>The Service does not constitute financial advice, investment advice, tax advice, or any other form of regulated advice within the meaning of the Financial Services and Markets Act 2000.</strong></p>
          <p class="text-neutral-500 leading-relaxed mb-4">Fynla is not authorised or regulated by the Financial Conduct Authority (FCA). The Service is not covered by the Financial Services Compensation Scheme (FSCS). You do not have access to the Financial Ombudsman Service in relation to Fynla's services.</p>
          <p class="text-neutral-500 leading-relaxed mb-4">All calculations, projections, and outputs are estimates only. They are provided for general educational and planning purposes and should not be relied upon as the basis for any financial decision. Tax rules, financial regulations, and market conditions change frequently, and the Service may not reflect the most current rules or your individual circumstances.</p>
          <p class="text-neutral-500 leading-relaxed mb-5">You should always seek independent financial advice from an FCA-regulated adviser before making important financial decisions.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Beta Status</h3>
          <p class="text-neutral-500 leading-relaxed">The Service is currently in beta. During the beta period, features may change, data may be deleted or altered, and the Service may experience interruptions. We will provide reasonable notice of any material changes where practicable.</p>
        </section>

        <!-- 3. Eligibility -->
        <section class="mb-10" id="eligibility">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">3. Eligibility</h2>
          <p class="text-neutral-500 leading-relaxed mb-4">To use the Service, you must:</p>
          <ul class="list-disc pl-6 space-y-2">
            <li class="text-neutral-500 leading-relaxed">Be at least 18 years of age.</li>
            <li class="text-neutral-500 leading-relaxed">Be resident in the United Kingdom (the Service is designed for UK tax rules and financial regulations; users outside the UK may register but the Service may not be suitable for their circumstances).</li>
            <li class="text-neutral-500 leading-relaxed">Provide accurate and complete information during registration and when using the Service.</li>
            <li class="text-neutral-500 leading-relaxed">Not use the Service for any unlawful purpose.</li>
          </ul>
        </section>

        <!-- 4. Your Account -->
        <section class="mb-10" id="your-account">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">4. Your Account</h2>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Registration</h3>
          <p class="text-neutral-500 leading-relaxed mb-5">To access the full Service, you must create an account by providing your name, email address, and a password. A verification code will be sent to your email address, which you must enter to complete registration. You are responsible for maintaining the confidentiality of your login credentials and for all activity that occurs under your account. You must notify us immediately at <a href="mailto:support@fynla.org" class="text-violet-600 hover:text-violet-800 underline">support@fynla.org</a> if you become aware of any unauthorised use of your account.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Accuracy of Information</h3>
          <p class="text-neutral-500 leading-relaxed mb-5">You are responsible for the accuracy of the information you provide. The quality and relevance of the Service's outputs depend on the accuracy of your inputs. We are not liable for any inaccuracies in the Service's outputs that result from incorrect or incomplete information provided by you.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">One Account Per Person</h3>
          <p class="text-neutral-500 leading-relaxed">Each individual may hold only one account. Spouse and partner accounts are separate accounts that may be linked for joint financial planning purposes where both parties consent.</p>
        </section>

        <!-- 5. Subscription Plans and Pricing -->
        <section class="mb-10" id="pricing">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">5. Subscription Plans and Pricing</h2>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Plans</h3>
          <p class="text-neutral-500 leading-relaxed mb-4">The Service offers the following subscription plans. Launch special pricing is available to the first 500 subscribers and will apply for the duration of your subscription, including renewals, for as long as it remains active.</p>
          <div class="overflow-x-auto mb-6">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b-2 border-light-gray">
                  <th class="text-left py-3 pr-4 font-semibold text-horizon-500">Plan</th>
                  <th class="text-left py-3 pr-4 font-semibold text-horizon-500">Launch Monthly</th>
                  <th class="text-left py-3 pr-4 font-semibold text-horizon-500">Launch Yearly</th>
                  <th class="text-left py-3 pr-4 font-semibold text-horizon-500">Regular Monthly</th>
                  <th class="text-left py-3 font-semibold text-horizon-500">Regular Yearly</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-savannah-100">
                <tr>
                  <td class="py-3 pr-4 text-neutral-500">Student</td>
                  <td class="py-3 pr-4 text-neutral-500">&pound;3.99</td>
                  <td class="py-3 pr-4 text-neutral-500">&pound;30.00</td>
                  <td class="py-3 pr-4 text-neutral-500">&pound;4.99</td>
                  <td class="py-3 text-neutral-500">&pound;45.00</td>
                </tr>
                <tr>
                  <td class="py-3 pr-4 text-neutral-500">Standard</td>
                  <td class="py-3 pr-4 text-neutral-500">&pound;10.99</td>
                  <td class="py-3 pr-4 text-neutral-500">&pound;100.00</td>
                  <td class="py-3 pr-4 text-neutral-500">&pound;14.99</td>
                  <td class="py-3 text-neutral-500">&pound;135.00</td>
                </tr>
                <tr>
                  <td class="py-3 pr-4 text-neutral-500">Family</td>
                  <td class="py-3 pr-4 text-neutral-500">&pound;14.99</td>
                  <td class="py-3 pr-4 text-neutral-500">&pound;150.00</td>
                  <td class="py-3 pr-4 text-neutral-500">&pound;21.99</td>
                  <td class="py-3 text-neutral-500">&pound;199.00</td>
                </tr>
                <tr>
                  <td class="py-3 pr-4 text-neutral-500">Pro</td>
                  <td class="py-3 pr-4 text-neutral-500">&pound;19.99</td>
                  <td class="py-3 pr-4 text-neutral-500">&pound;200.00</td>
                  <td class="py-3 pr-4 text-neutral-500">&pound;29.99</td>
                  <td class="py-3 text-neutral-500">&pound;269.99</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="text-neutral-500 leading-relaxed mb-5">All prices are in pounds sterling (GBP) and are inclusive of VAT where applicable. Once the launch special allocation has been filled, new subscribers will be charged at the regular price.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Free Trial</h3>
          <p class="text-neutral-500 leading-relaxed mb-5">New accounts receive a 7-day free trial with full access to the Service. No payment information is required to start a trial. When the trial expires, your account is downgraded to a free tier with limited access and you will not be able to access planning tools, dashboards, or document uploads until you subscribe. If you do not subscribe within 30 days of your trial ending, your account and all associated data will be permanently deleted.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Payment Processing</h3>
          <p class="text-neutral-500 leading-relaxed mb-5">We do not receive, process, or store your payment card details. Our payment processor's terms of service apply to all payment transactions. We are not responsible for payment processing errors or interruptions caused by third-party payment providers.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Auto-Renewal</h3>
          <p class="text-neutral-500 leading-relaxed mb-5">Subscriptions renew automatically at the end of each billing period (monthly or yearly) at the then-current price. We will send you a reminder before your subscription renews. You may cancel auto-renewal at any time before the next billing date.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Price Changes</h3>
          <p class="text-neutral-500 leading-relaxed mb-5">We may change our subscription prices from time to time. We will give you at least 30 days' notice of any price increase. You may cancel your subscription before the new price takes effect, and the current price will apply for the remainder of your existing billing period.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Failed Payments</h3>
          <p class="text-neutral-500 leading-relaxed">If a payment fails, we will notify you and may retry the payment. If payment is not received within a reasonable period, we may suspend your access to paid features until payment is made.</p>
        </section>

        <!-- 6. Your Right to Cancel -->
        <section class="mb-10" id="cancellation">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">6. Your Right to Cancel</h2>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">14-Day Cooling-Off Period</h3>
          <p class="text-neutral-500 leading-relaxed mb-4">Under the Consumer Contracts (Information, Cancellation and Additional Charges) Regulations 2013, you have the right to cancel your subscription within 14 days of subscribing, without giving any reason.</p>
          <p class="text-neutral-500 leading-relaxed mb-4">To exercise this right, you must inform us of your decision to cancel by email to <a href="mailto:support@fynla.org" class="text-violet-600 hover:text-violet-800 underline">support@fynla.org</a>. You may use a clear written statement of your decision to cancel.</p>
          <p class="text-neutral-500 leading-relaxed mb-4">If you cancel within the 14-day period, we will reimburse all payments received from you within 14 days of receiving your cancellation notice using the same means of payment you used for the original transaction.</p>
          <p class="text-neutral-500 leading-relaxed mb-5">If you requested that the Service begin during the cooling-off period (which you do by using the Service after subscribing), you acknowledge that you will lose your right to cancel once the service has been fully performed during that period. If you cancel before the period has fully elapsed, you may be charged a proportionate amount for the service provided up to the point of cancellation.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Cancellation After the Cooling-Off Period</h3>
          <p class="text-neutral-500 leading-relaxed mb-5">You may cancel your subscription at any time after the 14-day cooling-off period. Cancellation takes effect at the end of your current billing period. You will continue to have access to paid features until the end of the period you have already paid for. We do not offer partial refunds for unused portions of a billing period after the cooling-off period has expired. If you do not resubscribe within 30 days of your subscription ending, your account and all associated data will be permanently deleted.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">How to Cancel</h3>
          <p class="text-neutral-500 leading-relaxed">You may cancel your subscription through your account settings or by emailing <a href="mailto:support@fynla.org" class="text-violet-600 hover:text-violet-800 underline">support@fynla.org</a>. We will process cancellation requests promptly and confirm your cancellation by email.</p>
        </section>

        <!-- 7. Document Upload and AI Processing -->
        <section class="mb-10" id="ai-processing">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">7. Document Upload and AI Processing</h2>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">How Document Processing Works</h3>
          <p class="text-neutral-500 leading-relaxed mb-5">The Service allows you to upload financial documents (pension statements, insurance policies, investment statements, mortgage statements, and savings statements) for automated data extraction. Uploaded documents are processed using artificial intelligence provided by Anthropic to extract relevant financial data.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Upload Limits</h3>
          <p class="text-neutral-500 leading-relaxed mb-4">Document upload limits vary by plan:</p>
          <ul class="list-disc pl-6 mb-5 space-y-2">
            <li class="text-neutral-500 leading-relaxed"><strong>Student plan:</strong> 1 upload per day, 3 uploads per month.</li>
            <li class="text-neutral-500 leading-relaxed"><strong>Standard plan:</strong> 1 upload per day, 5 uploads per month.</li>
            <li class="text-neutral-500 leading-relaxed"><strong>Family plan:</strong> 2 uploads per day, 10 uploads per month.</li>
            <li class="text-neutral-500 leading-relaxed"><strong>Pro plan:</strong> Unlimited uploads.</li>
          </ul>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">AI Processing Disclaimers</h3>
          <p class="text-neutral-500 leading-relaxed mb-4">AI-generated outputs may contain errors or inaccuracies. You should verify all data extracted from uploaded documents before relying on it for financial planning purposes. We do not guarantee the accuracy, completeness, or reliability of AI-generated outputs.</p>
          <p class="text-neutral-500 leading-relaxed">Uploaded document content is transmitted to Anthropic's servers in the United States over encrypted connections. Anthropic's API usage policy states that data sent via their API is not used to train their models. You are responsible for ensuring that you have the right to upload any documents you submit. For further details on international data transfers, see our <router-link to="/privacy" class="text-violet-600 hover:text-violet-800 underline">Privacy Policy</router-link>.</p>
        </section>

        <!-- 8. Acceptable Use -->
        <section class="mb-10" id="acceptable-use">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">8. Acceptable Use</h2>
          <p class="text-neutral-500 leading-relaxed mb-4">You agree not to:</p>
          <ul class="list-disc pl-6 space-y-2">
            <li class="text-neutral-500 leading-relaxed">Use the Service for any unlawful purpose or in violation of any applicable law or regulation.</li>
            <li class="text-neutral-500 leading-relaxed">Provide false, misleading, or inaccurate information.</li>
            <li class="text-neutral-500 leading-relaxed">Attempt to gain unauthorised access to the Service, other users' accounts, or our systems.</li>
            <li class="text-neutral-500 leading-relaxed">Interfere with or disrupt the Service or its infrastructure.</li>
            <li class="text-neutral-500 leading-relaxed">Reverse-engineer, decompile, disassemble, or otherwise attempt to derive the source code of the Service.</li>
            <li class="text-neutral-500 leading-relaxed">Use the Service to develop a competing product or service.</li>
            <li class="text-neutral-500 leading-relaxed">Use automated tools, bots, scrapers, or similar technology to access the Service without our prior written consent.</li>
            <li class="text-neutral-500 leading-relaxed">Share your account credentials with any other person.</li>
          </ul>
          <p class="text-neutral-500 leading-relaxed mt-4">We may suspend or terminate your account if you breach these terms of acceptable use. See Section 13 for details.</p>
        </section>

        <!-- 9. Intellectual Property -->
        <section class="mb-10" id="intellectual-property">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">9. Intellectual Property</h2>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Our Rights</h3>
          <p class="text-neutral-500 leading-relaxed mb-5">The Service, including all software, design, text, graphics, logos, and other content (excluding your personal data), is owned by or licensed to Fynla Ltd and is protected by copyright, trademark, and other intellectual property laws. We grant you a limited, non-exclusive, non-transferable, revocable licence to access and use the Service for your personal, non-commercial financial planning purposes, subject to these Terms. You may not copy, modify, distribute, sell, lease, reverse-engineer, or create derivative works from any part of the Service without our prior written consent.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Your Data</h3>
          <p class="text-neutral-500 leading-relaxed mb-5">You retain ownership of all personal and financial data you input into the Service. We do not claim ownership of your data. We use your data solely to provide the Service to you, as described in our <router-link to="/privacy" class="text-violet-600 hover:text-violet-800 underline">Privacy Policy</router-link>.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">AI-Generated Outputs</h3>
          <p class="text-neutral-500 leading-relaxed mb-5">Calculations, projections, reports, and other outputs generated by the Service (including AI-extracted data) are provided to you as part of the Service for your personal use. These outputs are derived from your data and general financial models and are not warranted to be free from errors.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Feedback</h3>
          <p class="text-neutral-500 leading-relaxed">If you provide us with feedback, suggestions, or ideas about the Service, you grant us a non-exclusive, royalty-free, worldwide licence to use, modify, and incorporate that feedback into the Service without obligation to you.</p>
        </section>

        <!-- 10. Limitation of Liability -->
        <section class="mb-10" id="liability">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">10. Limitation of Liability</h2>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">What We Do Not Exclude</h3>
          <p class="text-neutral-500 leading-relaxed mb-4">Nothing in these Terms excludes or limits our liability for:</p>
          <ul class="list-disc pl-6 mb-5 space-y-2">
            <li class="text-neutral-500 leading-relaxed">Death or personal injury caused by our negligence.</li>
            <li class="text-neutral-500 leading-relaxed">Fraud or fraudulent misrepresentation.</li>
            <li class="text-neutral-500 leading-relaxed">Any liability that cannot be excluded or limited under applicable law, including your statutory rights under the Consumer Rights Act 2015.</li>
          </ul>
          <p class="text-neutral-500 leading-relaxed mb-5">Under the Consumer Rights Act 2015, the Service must be performed with reasonable care and skill, and information provided about the Service must be accurate. Your statutory rights are not affected by these Terms.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">What We Limit</h3>
          <p class="text-neutral-500 leading-relaxed mb-4">Subject to the paragraph above:</p>
          <ul class="list-disc pl-6 mb-5 space-y-3">
            <li class="text-neutral-500 leading-relaxed">We are not liable for any indirect, incidental, special, consequential, or punitive damages, including but not limited to loss of profits, loss of business, loss of data, loss of opportunity, or financial losses arising from any investment, tax, or other financial decisions you make based on the Service's outputs.</li>
            <li class="text-neutral-500 leading-relaxed">Our total aggregate liability to you for any claims arising out of or relating to the Service is limited to the total amount you have paid us for the Service in the 12 months preceding the claim. If you are using the free tier or trial, our total liability is limited to &pound;50.</li>
            <li class="text-neutral-500 leading-relaxed">We are not liable for any loss or damage arising from the accuracy, completeness, or timeliness of the Service's calculations, projections, or outputs, except where caused by our failure to exercise reasonable care and skill.</li>
            <li class="text-neutral-500 leading-relaxed">We are not liable for any loss or damage arising from the actions or omissions of third-party service providers, including Anthropic, GetAddress.io, and SiteGround.</li>
          </ul>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Your Responsibility</h3>
          <p class="text-neutral-500 leading-relaxed">You are solely responsible for all financial decisions you make. The Service is a planning tool and does not replace professional financial advice. You acknowledge that financial planning involves inherent uncertainty and that actual outcomes may differ materially from projections.</p>
        </section>

        <!-- 11. Service Availability -->
        <section class="mb-10" id="availability">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">11. Service Availability</h2>
          <p class="text-neutral-500 leading-relaxed mb-4">The Service is provided on an "as available" basis. We do not guarantee that the Service will be available at all times or without interruption. We may temporarily suspend access to the Service for maintenance, updates, or for reasons beyond our reasonable control.</p>
          <p class="text-neutral-500 leading-relaxed mb-4">We will endeavour to give reasonable notice of planned maintenance where practicable. Nothing in this section affects your statutory rights under the Consumer Rights Act 2015.</p>
          <p class="text-neutral-500 leading-relaxed">We reserve the right to modify, update, or discontinue any feature of the Service at any time. Where changes materially affect your use of the Service, we will give you reasonable notice and, where applicable, the option to cancel your subscription.</p>
        </section>

        <!-- 12. Data Protection -->
        <section class="mb-10" id="data-protection">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">12. Data Protection</h2>
          <p class="text-neutral-500 leading-relaxed mb-4">Your personal data is processed in accordance with our <router-link to="/privacy" class="text-violet-600 hover:text-violet-800 underline">Privacy Policy</router-link>, which forms part of these Terms. By using the Service, you acknowledge that you have read and understood our Privacy Policy.</p>
          <p class="text-neutral-500 leading-relaxed mb-4">Key points:</p>
          <ul class="list-disc pl-6 space-y-2">
            <li class="text-neutral-500 leading-relaxed">We collect and process your personal data to provide the Service, in accordance with the UK General Data Protection Regulation and the Data Protection Act 2018.</li>
            <li class="text-neutral-500 leading-relaxed">You have the right to access, rectify, erase, restrict, port, and object to the processing of your personal data.</li>
            <li class="text-neutral-500 leading-relaxed">You can export your data in JSON or CSV format at any time through the Service.</li>
            <li class="text-neutral-500 leading-relaxed">You can delete your financial data or your entire account at any time through the Service.</li>
            <li class="text-neutral-500 leading-relaxed">You may lodge a complaint with the Information Commissioner's Office if you are not satisfied with how we handle your data.</li>
          </ul>
        </section>

        <!-- 13. Account Suspension and Termination -->
        <section class="mb-10" id="termination">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">13. Account Suspension and Termination</h2>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">By You</h3>
          <p class="text-neutral-500 leading-relaxed mb-4">You may close your account at any time through your account settings or by emailing <a href="mailto:support@fynla.org" class="text-violet-600 hover:text-violet-800 underline">support@fynla.org</a>. Upon account closure:</p>
          <ul class="list-disc pl-6 mb-5 space-y-2">
            <li class="text-neutral-500 leading-relaxed">Your subscription (if any) will be cancelled.</li>
            <li class="text-neutral-500 leading-relaxed">Your financial planning data will be deleted immediately.</li>
            <li class="text-neutral-500 leading-relaxed">Your account and profile data will be retained for 30 days and then permanently deleted.</li>
            <li class="text-neutral-500 leading-relaxed">You may request an export of your data before closing your account.</li>
          </ul>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">By Us</h3>
          <p class="text-neutral-500 leading-relaxed mb-4">We may suspend or terminate your account if:</p>
          <ul class="list-disc pl-6 mb-5 space-y-2">
            <li class="text-neutral-500 leading-relaxed">You breach these Terms, including the acceptable use provisions in Section 8.</li>
            <li class="text-neutral-500 leading-relaxed">You fail to pay subscription fees when due after reasonable notice and opportunity to rectify.</li>
            <li class="text-neutral-500 leading-relaxed">We are required to do so by law or regulatory order.</li>
            <li class="text-neutral-500 leading-relaxed">We reasonably believe your account has been compromised or is being used fraudulently.</li>
          </ul>
          <p class="text-neutral-500 leading-relaxed mb-5">Except in cases of serious breach, illegal activity, or legal obligation, we will give you at least 14 days' notice before terminating your account and provide you with an opportunity to export your data.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Effect of Termination</h3>
          <p class="text-neutral-500 leading-relaxed">Upon termination, your licence to use the Service ends immediately. Sections that by their nature should survive termination will continue to apply, including Sections 9 (Intellectual Property), 10 (Limitation of Liability), 14 (Governing Law), and 15 (Dispute Resolution).</p>
        </section>

        <!-- 14. Governing Law -->
        <section class="mb-10" id="governing-law">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">14. Governing Law</h2>
          <p class="text-neutral-500 leading-relaxed">These Terms are governed by and construed in accordance with the laws of England and Wales. If you are a consumer, you will benefit from any mandatory provisions of the law of the country in which you are resident. Nothing in these Terms affects your rights as a consumer to rely on such mandatory provisions of local law, nor your right to bring proceedings in the courts of your habitual residence within the United Kingdom.</p>
        </section>

        <!-- 15. Dispute Resolution -->
        <section class="mb-10" id="disputes">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">15. Dispute Resolution</h2>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Contact Us First</h3>
          <p class="text-neutral-500 leading-relaxed mb-5">If you have a complaint or dispute, please contact us at <a href="mailto:support@fynla.org" class="text-violet-600 hover:text-violet-800 underline">support@fynla.org</a>. We will acknowledge your complaint within 5 working days and aim to resolve it within 14 working days.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Alternative Dispute Resolution</h3>
          <p class="text-neutral-500 leading-relaxed mb-5">Under the Alternative Dispute Resolution for Consumer Disputes (Competent Authorities and Information) Regulations 2015, we are required to inform you of an ADR entity that would be competent to deal with a dispute should you wish to use it. You may refer a dispute to the Centre for Effective Dispute Resolution (CEDR) at <a href="https://www.cedr.com" target="_blank" rel="noopener noreferrer" class="text-violet-600 hover:text-violet-800 underline">cedr.com</a>. We are not obliged to use CEDR or any other ADR entity, and we will confirm our position if you raise a dispute.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Courts</h3>
          <p class="text-neutral-500 leading-relaxed">If we cannot resolve a dispute through our complaints procedure, either party may bring proceedings in the courts of England and Wales. If you are a consumer resident in Scotland or Northern Ireland, you may also bring proceedings in your local courts.</p>
        </section>

        <!-- 16. Changes to These Terms -->
        <section class="mb-10" id="changes">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">16. Changes to These Terms</h2>
          <p class="text-neutral-500 leading-relaxed mb-4">We may update these Terms from time to time to reflect changes in our practices, technology, legal requirements, or other factors. Where we make material changes, we will:</p>
          <ul class="list-disc pl-6 mb-4 space-y-2">
            <li class="text-neutral-500 leading-relaxed">Give you at least 30 days' notice by email or through a prominent notice within the Service.</li>
            <li class="text-neutral-500 leading-relaxed">Provide you with the updated Terms before they take effect.</li>
            <li class="text-neutral-500 leading-relaxed">Give you the opportunity to cancel your subscription before the new Terms take effect if you do not agree to them.</li>
          </ul>
          <p class="text-neutral-500 leading-relaxed">Your continued use of the Service after the updated Terms take effect constitutes your acceptance of the changes. If you do not agree to the updated Terms, you must stop using the Service and cancel your subscription.</p>
        </section>

        <!-- 17. Third-Party Services -->
        <section class="mb-10" id="third-parties">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">17. Third-Party Services</h2>
          <p class="text-neutral-500 leading-relaxed mb-4">The Service integrates with the following third-party services:</p>
          <ul class="list-disc pl-6 mb-4 space-y-2">
            <li class="text-neutral-500 leading-relaxed"><strong>Anthropic</strong> &mdash; AI-powered document data extraction.</li>
            <li class="text-neutral-500 leading-relaxed"><strong>GetAddress.io</strong> &mdash; UK postcode lookup.</li>
            <li class="text-neutral-500 leading-relaxed"><strong>SiteGround</strong> &mdash; Web hosting and email delivery.</li>
          </ul>
          <p class="text-neutral-500 leading-relaxed">These third-party services are subject to their own terms of service and privacy policies. We are not responsible for the availability, accuracy, or conduct of third-party services, and we do not warrant that they will be uninterrupted or error-free.</p>
        </section>

        <!-- 18. General Provisions -->
        <section class="mb-10" id="general">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">18. General Provisions</h2>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Entire Agreement</h3>
          <p class="text-neutral-500 leading-relaxed mb-5">These Terms, together with our <router-link to="/privacy" class="text-violet-600 hover:text-violet-800 underline">Privacy Policy</router-link>, constitute the entire agreement between you and Fynla Ltd in relation to your use of the Service. They supersede all prior agreements, representations, and understandings. Nothing in this clause excludes liability for pre-contractual statements made fraudulently or upon which you reasonably relied.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Severability</h3>
          <p class="text-neutral-500 leading-relaxed mb-5">If any provision of these Terms is found to be invalid, unlawful, or unenforceable, that provision shall be deemed modified to the minimum extent necessary to make it valid and enforceable, or if modification is not possible, it shall be severed from these Terms. The remaining provisions shall continue in full force and effect.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Waiver</h3>
          <p class="text-neutral-500 leading-relaxed mb-5">Our failure to enforce any right or provision of these Terms does not constitute a waiver of that right or provision.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Assignment</h3>
          <p class="text-neutral-500 leading-relaxed mb-5">We may assign or transfer our rights and obligations under these Terms to another organisation, provided this does not adversely affect your rights. We will notify you if this happens. You may not assign or transfer your rights or obligations under these Terms without our prior written consent.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">Force Majeure</h3>
          <p class="text-neutral-500 leading-relaxed mb-5">Neither party shall be liable for any failure or delay in performing obligations under these Terms where such failure or delay results from circumstances beyond that party's reasonable control, including but not limited to natural disasters, acts of government, pandemic, internet or telecommunications failures, or power outages.</p>

          <h3 class="text-base font-semibold text-horizon-500 mb-2">No Client Money</h3>
          <p class="text-neutral-500 leading-relaxed">Fynla does not handle, hold, or manage client money. We do not have access to your bank accounts, investment accounts, or pension funds. The Service is a planning and information tool only.</p>
        </section>

        <!-- 19. Contact Us -->
        <section class="mb-10" id="contact">
          <h2 class="text-xl font-bold text-horizon-500 mb-4 pb-2 border-b border-light-gray">19. Contact Us</h2>
          <p class="text-neutral-500 leading-relaxed mb-4">For any questions about these Terms:</p>
          <div class="pl-4 border-l-2 border-light-gray mb-4">
            <p class="text-neutral-500">Email: <a href="mailto:support@fynla.org" class="text-violet-600 hover:text-violet-800 underline">support@fynla.org</a></p>
            <p class="text-neutral-500">Postal address: Fynla Limited, 124 City Road, London, EC1V 2NX</p>
          </div>
        </section>


      </div>
    </div>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'TermsOfServicePage',

  components: {
    PublicLayout,
  },

  mounted() {
    document.title = 'Terms of service | Fynla';
    document.querySelector('meta[name="description"]')?.setAttribute('content', 'Read Fynla\'s terms of service. Understand your rights and responsibilities when using the Fynla personal finance platform.');
  },
};
</script>
