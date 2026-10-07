@extends('installer.imaster')

@section('title', 'Welcome to Exam Frame')

@section('body')
    @parent
@endsection

@section('content')
    <div class="text-center mb-4">
        <img src="{{ URL::asset('build/images/logo-sm-1.png') }}" alt="Exam Frame Logo" style="width: 120px;">
        <h3 class="mt-3">Welcome to <strong>Exam Frame</strong></h3>
        <p class="text-muted">Open Source Exam Engine</p>
    </div>

    <div class="col-12 col-md-8 col-lg-6">
        <div class="card shadow-lg installer-card">
            <div class="card-body p-4 p-md-5">
                <h5 class="card-title text-center mb-3">Installation Wizard</h5>
                <p class="card-text text-center text-muted mb-4">Follow the steps to complete the installation. Please review the license agreement before proceeding.</p>

                {{-- Yeh button ab Modal ko trigger karega --}}
                <button type="button" class="btn btn-primary w-100 btn-lg" data-bs-toggle="modal"
                    data-bs-target="#licenseModal">
                    Get Started
                </button>
            </div>
        </div>
    </div>

    <footer class="text-center mt-4 trust-footer">
        <p class="mb-2">
            <span class="trust-badge">Envato Approved</span>
            <span class="trust-badge">12+ Years Experience</span>
        </p>
        <p>
            <a href="https://eduexpression.com" target="_blank">eduexpression.com</a> |
            <a href="mailto:help@eduexpression.com">help@eduexpression.com</a> |
            <a href="https://wa.me/918881642322" target="_blank">WhatsApp: +91 - 88816 42322</a>
        </p>
        <p>&copy; 2012 - {{ date('Y') }} Skill Stride Ventures Pvt. Ltd. All rights reserved.</p>
    </footer>


    <div class="modal fade" id="licenseModal" tabindex="-1" aria-labelledby="licenseModalLabel" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="licenseModalLabel">End User License Agreement (EULA)</h5>
                </div>
                <div class="modal-body">
                    <p><strong>Version 1.0 — Last Updated: October 2025</strong></p>
                    <p>
                        Product Name: Exam Frame – Open Source Exam Engine<br>
                        Company: Skill Stride Ventures Pvt. Ltd.<br>
                        Registered Office: Hazratganj, Lucknow, Uttar Pradesh, India<br>
                        Email: help@eduexpression.com<br>
                        Website: https://eduexpression.com
                    </p>
                    <hr>
                    <div class="license-content">
                        <pre>
1. Introduction
This End User License Agreement (“Agreement”) is a legal agreement between Skill Stride Ventures Pvt. Ltd. (“Licensor”, “we”, “our”, or “us”) and the individual or organization (“Licensee”, “you”, or “your”) who purchases, installs, or uses the Exam Frame – Open Source Exam Engine (“Software”).
By installing, downloading, or using the Software, you agree to be bound by the terms of this Agreement. If you do not agree, you must not install or use the Software.

2. License Grant
Upon purchase, Skill Stride Ventures Pvt. Ltd. grants you a non-exclusive, non-transferable, single-domain license to install and use the Software on one (1) domain or server instance only.
This license is for use by the purchasing organization or individual and may not be transferred, sublicensed, or shared with third parties unless prior written approval is obtained from Skill Stride Ventures Pvt. Ltd.

3. Ownership and Intellectual Property
The Software, including all source code, design, database structure, documentation, and trademarks, remains the sole property of Skill Stride Ventures Pvt. Ltd.
You are granted only the right to use the Software under the terms of this Agreement. No ownership or intellectual property rights are transferred to you.
Unauthorized copying, resale, modification, or redistribution of the Software, in whole or in part, is strictly prohibited and constitutes a violation of the Indian Copyright Act, 1957 and the Information Technology Act, 2000.

4. Permitted Use
Under this license, you are permitted to:
- Install the Software on one (1) production domain or live server.
- Customize visuals, branding, and text for your internal use.
- Take one-time free installation support or use provided documentation.
- Use the Software for your own educational or commercial exam operations.

5. Restrictions
You may not:
- Use one license on multiple domains, subdomains, or servers.
- Distribute, resell, rent, or sublicense the Software or its code.
- Remove or alter any copyright or ownership notice.
- Reverse engineer, decode, or decompile the Software for redistribution.
- Use the Software for unlawful or deceptive purposes.
Violation of these terms will lead to immediate license termination without refund or prior notice.

6. Installation and Support
First installation is free of charge (one-time setup).
Support Duration: Free technical support is provided for a minimum of 6 months and up to 1 year from the date of purchase, as per plan or agreement.
Any reinstallation, migration, or domain change after the first installation may incur additional service charges.
Support requests can be raised via our helpdesk or email: help@eduexpression.com

7. Updates and Maintenance
Licensees are eligible for free updates for two (2) years from the purchase date.
Major version upgrades or new product variants may be offered separately under new pricing or license terms.
If you modify the Software source code independently, support and update eligibility may be affected.

8. Refund Policy
Since the Software is a digital product, all sales are final.
Refunds are not issued once the product or source code has been delivered or downloaded.
We encourage users to test our public demo before purchasing:
- Admin Demo
- Student Demo
If any technical issues occur, our support team will assist in resolving them promptly.

9. Data and Privacy
Your personal details collected during purchase or demo registration are handled as per our Privacy Policy.
We do not sell, rent, or share your data with third parties except as required for payment or support services.

10. Limitation of Liability
Skill Stride Ventures Pvt. Ltd. shall not be liable for:
- Loss of data or downtime caused by client server misconfiguration.
- Problems arising due to third-party hosting, plugins, or code modification.
- Indirect, incidental, or consequential damages resulting from the use of the Software.
Our total liability shall not exceed the actual amount paid by the Licensee for the Software.

11. Termination
Skill Stride Ventures Pvt. Ltd. reserves the right to suspend or terminate your license if you breach any terms of this Agreement.
Upon termination, you must immediately stop using the Software and remove all copies from your systems.

12. Governing Law and Jurisdiction
This Agreement shall be governed by and construed in accordance with the laws of India.
Any disputes arising from this Agreement shall fall under the exclusive jurisdiction of the courts in Lucknow, Uttar Pradesh, India.

13. Contact Information
For license, legal, or compliance-related queries, please contact:
help@eduexpression.com
https://eduexpression.com

© 2012–2025 Skill Stride Ventures Pvt. Ltd.
All Rights Reserved.
Exam Frame™ is a registered product of Skill Stride Ventures Pvt. Ltd.
                        </pre>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="form-check w-100">
                        <input class="form-check-input" type="checkbox" value="" id="agreeCheckbox">
                        <label class="form-check-label" for="agreeCheckbox">
                            I have read, understood, and agree to the terms and conditions of the License Agreement.
                        </label>
                    </div>
                    {{-- ✅ ROUTE CHANGED: Ab ye Purchase Code page par jayega --}}
                    <a href="{{ route('installer.purchase_code') }}" class="btn btn-primary w-100 mt-2 disabled"
                        id="continueButton">
                        Agree & Continue
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const agreeCheckbox = document.getElementById('agreeCheckbox');
            const continueButton = document.getElementById('continueButton');

            agreeCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    continueButton.classList.remove('disabled');
                } else {
                    continueButton.classList.add('disabled');
                }
            });
        });
    </script>
@endsection