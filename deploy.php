<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'POST required']));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['business_name'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Invalid content.json']));
}

define('WP_USE_THEMES', false);
define('ABSPATH', __DIR__ . '/');
require_once ABSPATH . 'wp-load.php';

if (!function_exists('wp_insert_post')) {
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'WordPress not loaded']));
}

require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/theme.php';
require_once ABSPATH . 'wp-admin/includes/theme-install.php';
require_once ABSPATH . 'wp-admin/includes/class-theme-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

class Silent_Skin extends WP_Upgrader_Skin {
    public function feedback($string, ...$args) {}
    public function header() {}
    public function footer() {}
}

function install_wp_plugin($slug) {
    if (is_plugin_active("$slug/$slug.php") || is_plugin_active("$slug/plugin.php")) return true;
    $installed = get_plugins();
    $main_file = null;
    foreach ($installed as $file => $data) {
        if (strpos($file, "$slug/") === 0) { $main_file = $file; break; }
    }
    if (!$main_file) {
        $api = plugins_api('plugin_information', ['slug' => $slug, 'fields' => ['sections' => false]]);
        if (is_wp_error($api)) return false;
        $upgrader = new Plugin_Upgrader(new Silent_Skin());
        $result = $upgrader->install($api->download_link);
        if (!$result || is_wp_error($result)) return false;
        wp_cache_delete('plugins', 'plugins');
        $installed = get_plugins();
        foreach ($installed as $file => $data) {
            if (strpos($file, "$slug/") === 0) { $main_file = $file; break; }
        }
    }
    if ($main_file) { activate_plugin($main_file); return true; }
    return false;
}

function install_wp_theme($slug) {
    $theme = wp_get_theme($slug);
    if ($theme->exists()) { switch_theme($slug); return true; }
    $api = themes_api('theme_information', ['slug' => $slug, 'fields' => ['sections' => false]]);
    if (is_wp_error($api)) return false;
    $upgrader = new Theme_Upgrader(new Silent_Skin());
    $result = $upgrader->install($api->download_link);
    if (!$result || is_wp_error($result)) return false;
    switch_theme($slug);
    return true;
}

function hx() { return substr(bin2hex(random_bytes(4)), 0, 7); }

function ew($type, $settings) {
    return ['id' => hx(), 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => []];
}

function esec($cols, $bg = null, $pt = '80', $pb = '80') {
    $s = ['padding' => ['unit' => 'px', 'top' => $pt, 'bottom' => $pb]];
    if ($bg) { $s['background_background'] = 'classic'; $s['background_color'] = $bg; }
    return ['id' => hx(), 'elType' => 'section', 'settings' => (object)$s, 'elements' => $cols];
}

function ecol($widgets, $sz = 100) {
    return ['id' => hx(), 'elType' => 'column', 'settings' => ['_column_size' => $sz], 'elements' => $widgets];
}

function es1($widgets, $bg = null, $pt = '80', $pb = '80') {
    return esec([ecol($widgets)], $bg, $pt, $pb);
}

function eh1($t) {
    return ew('heading', ['title' => $t, 'header_size' => 'h1', 'align' => 'center', 'title_color' => '#FFFFFF']);
}

function eh2($t) {
    return ew('heading', ['title' => $t, 'header_size' => 'h2', 'align' => 'center']);
}

function ep($t) {
    return ew('text-editor', ['editor' => "<p>$t</p>"]);
}

function ebtn($t, $u, $bg = '#2E7D32') {
    return ew('button', [
        'text' => $t, 'link' => ['url' => $u, 'is_external' => false],
        'align' => 'center', 'background_color' => $bg, 'button_text_color' => '#FFFFFF',
        'border_radius' => ['unit' => 'px', 'top' => '5', 'right' => '5', 'bottom' => '5', 'left' => '5']
    ]);
}

function eib($icon, $title, $desc) {
    return ew('icon-box', [
        'selected_icon' => ['value' => $icon, 'library' => 'fa-solid'],
        'title_text' => $title, 'description_text' => $desc, 'position' => 'left'
    ]);
}

function eil($items) {
    $list = [];
    foreach ($items as $i) {
        $list[] = ['text' => $i['text'], 'selected_icon' => ['value' => $i['icon'] ?? 'fas fa-check', 'library' => 'fa-solid'], '_id' => hx()];
    }
    return ew('icon-list', ['icon_list' => $list]);
}

function eacc($items) {
    $tabs = [];
    foreach ($items as $i) {
        $tabs[] = ['_id' => hx(), 'tab_title' => $i['q'], 'tab_content' => $i['a']];
    }
    return ew('accordion', ['tabs' => $tabs]);
}

function emap($addr) {
    return ew('google_maps', ['address' => $addr, 'zoom' => ['size' => 14], 'height' => ['size' => 400]]);
}

function ehtm($code) { return ew('html', ['html' => $code]); }
function eimg($url = 'https://via.placeholder.com/800x400') { return ew('image', ['image' => ['url' => $url, 'id' => ''], 'image_size' => 'full']); }

function benefits_widgets($city) {
    return [
        eib('fas fa-certificate', '100% Licensed & Certified', "Our entire team is fully licensed and certified. We maintain current credentials and follow all industry best practices to deliver professional results."),
        eib('fas fa-map-marker-alt', "Local Knowledge & Experience", "With years serving $city and surrounding areas, we understand local conditions, regulations, and what works best for properties in our region."),
        eib('fas fa-shield-alt', 'Google Guaranteed', "We are a Google Guaranteed business, meaning Google backs our services with a satisfaction guarantee for added peace of mind."),
        eib('fas fa-clock', "10+ Years Serving $city", "For over a decade, we have been the trusted choice in $city. Our track record speaks to our reliability, quality, and commitment."),
        eib('fas fa-file-contract', 'Written Warranty on All Work', "Every project comes with a written warranty. We stand behind our work and address any concerns to ensure your complete satisfaction."),
        eib('fas fa-hard-hat', 'Fully Insured', "We carry comprehensive general liability and workers compensation insurance. Your property and our team are fully protected on every job."),
    ];
}

function home_faq($kw, $biz, $city, $phone, $areas) {
    $kl = strtolower($kw);
    return [
        ['q' => "How much does $kl cost in $city?", 'a' => "The cost of $kl varies depending on the scope of the project, accessibility, and specific requirements. We provide free, no-obligation estimates so you get an accurate price for your situation. Contact us at $phone for a personalized quote tailored to your needs."],
        ['q' => "How long does a typical $kl project take?", 'a' => "Most $kl projects are completed within one to three days, depending on complexity. During your free estimate, we provide a realistic timeline for your specific project. We always aim to minimize disruption to your daily routine while delivering quality results."],
        ['q' => "Is $biz licensed and insured?", 'a' => "$biz is fully licensed, insured, and certified. We carry comprehensive general liability and workers compensation insurance to protect your property and our team. We are happy to provide proof of insurance upon request."],
        ['q' => 'Do you offer free estimates?', 'a' => "Absolutely. We offer completely free, no-obligation estimates for all our services. One of our experienced professionals will visit your property, assess the work needed, and provide a detailed written estimate with transparent pricing."],
        ['q' => 'What areas do you serve?', 'a' => "We serve $city and the surrounding communities including $areas. If you are unsure whether we cover your area, give us a call at $phone and we will be happy to confirm availability."],
        ['q' => 'Do you offer emergency services?', 'a' => "Yes, we understand that emergencies can happen at any time. We offer emergency $kl services for situations that pose immediate safety risks. Call us at $phone and we will respond as quickly as possible."],
        ['q' => 'What warranty do you offer?', 'a' => "All our work comes with a written warranty. We stand behind the quality of our services and will address any concerns that arise after project completion. Ask about specific warranty terms during your free estimate."],
        ['q' => 'What payment methods do you accept?', 'a' => 'We accept cash, checks, and all major credit cards for your convenience. Payment is due upon completion of the work. For larger projects, we can discuss payment arrangements during the estimate process.'],
        ['q' => 'How do I prepare my property before service?', 'a' => 'Our team will advise you on any preparations needed during the estimate visit. Generally, we ask that vehicles be moved from the work area and pets be kept inside. We handle all the heavy lifting and equipment setup ourselves.'],
        ['q' => "Why should I choose $biz over other companies?", 'a' => "$biz combines years of local experience, licensed professionals, comprehensive insurance, and a written warranty on all work. We are Google Guaranteed, offer transparent pricing with no hidden fees, and our track record of satisfied customers in $city speaks for itself."],
    ];
}

function svc_faq($name, $biz, $city, $phone) {
    $nl = strtolower($name);
    return [
        ['q' => "How much does $nl cost in $city?", 'a' => "The cost of $nl in $city varies based on the scope of work, accessibility, and project complexity. We offer free estimates to provide you with an accurate price for your specific situation. Contact us at $phone for a personalized quote."],
        ['q' => "How long does $nl typically take?", 'a' => "Most $nl projects are completed within one to three days, depending on complexity and scope. During your free estimate, we provide a realistic timeline for your specific project. We always aim to minimize disruption to your routine."],
        ['q' => "Do I need a permit for $nl?", 'a' => "Permit requirements vary by location and scope of work. In $city, some $nl projects require permits while others do not. Our team handles the permit process when necessary, ensuring all work is compliant with local regulations."],
        ['q' => "Is $biz licensed and insured for $nl?", 'a' => "$biz is fully licensed, insured, and certified for all $nl services. We carry comprehensive general liability and workers compensation insurance. We are happy to provide proof of coverage upon request."],
        ['q' => "What happens if something goes wrong during $nl?", 'a' => "While our careful approach minimizes risks, we carry full insurance coverage for the rare occasion that an issue arises. Our warranty covers our workmanship, and our insurance covers any accidental property damage. Your protection is our priority."],
        ['q' => "Can you provide references for $nl?", 'a' => "Absolutely. We are proud of our work and happy to provide references from satisfied customers in $city. We also encourage you to check our Google reviews to see what our clients say about $biz."],
        ['q' => "When is the best time of year for $nl?", 'a' => "While we provide $nl services year-round, the best timing depends on your specific situation. Generally, scheduling during mild weather allows for optimal conditions. Contact us and we will advise on the ideal timing for your project."],
        ['q' => "Do you offer emergency $nl services?", 'a' => "Yes, we offer emergency $nl services for situations that pose immediate safety risks. Call us at $phone and our team will respond as quickly as possible to assess and address the emergency."],
    ];
}

function build_homepage($c) {
    $biz = $c['business_name'];
    $city = $c['city'];
    $phone = $c['phone'];
    $kw = $c['main_keyword'] ?? 'Services';
    $kl = strtolower($kw);
    $svcs = $c['services'] ?? [];
    $areas = implode(', ', $c['service_areas'] ?? [$city]);
    $dark = $c['colors']['secondary'] ?? '#1B5E20';
    $accent = $c['colors']['primary'] ?? '#2E7D32';
    $addr = $c['address'] ?? "$city, " . ($c['state'] ?? '');
    $tel = 'tel:' . preg_replace('/[\(\)\-\s]/', '', $phone);

    $S = [];

    $S[] = es1([
        eh1("Professional $kw in $city"),
        ew('heading', ['title' => "Licensed, Insured & Trusted by $city Residents", 'header_size' => 'h2', 'align' => 'center', 'title_color' => '#FFFFFF']),
        ebtn("Call Now $phone", $tel, '#FF6F00'),
        ew('text-editor', ['editor' => "<p style='color:#fff;text-align:center;'>Call $phone for a free estimate. We serve $city and surrounding areas with professional $kl you can count on.</p>"]),
    ], $dark, '120', '120');

    $S[] = es1([
        eh2("About $biz"),
        ep("$biz has been providing top-quality $kl to homeowners and businesses in $city and the surrounding areas for over a decade. Our experienced team combines industry expertise with a commitment to customer satisfaction that sets us apart. We take pride in delivering honest assessments, fair pricing, and exceptional results on every project we undertake."),
        eib('fas fa-certificate', 'Licensed & Certified', "Our team is fully licensed and certified for all $kl in $city. We maintain current credentials and follow all industry standards and safety protocols."),
        eib('fas fa-map-marker-alt', 'Local Experience', "With years of serving $city and neighboring communities, we understand local conditions, soil types, weather patterns, and regulations that affect your property."),
        eimg(),
    ]);

    $svc_w = [eh2('Our Services'), ep("We offer a comprehensive range of professional $kl to keep your property safe, beautiful, and well-maintained. Each service is performed by our licensed and insured team using professional-grade equipment.")];
    foreach ($svcs as $svc) {
        $desc = $svc['description'] ?? "Professional " . strtolower($svc['name']) . " services for residential and commercial properties. Contact us for a free estimate.";
        $svc_w[] = eib('fas fa-leaf', $svc['name'], $desc);
    }
    $svc_w[] = ebtn('View All Services', '/services/');
    $S[] = es1($svc_w);

    $S[] = es1([
        eh2("Need $kw Today? Call Now!"),
        ep("Do not wait until it is too late. Whether you need emergency service or want to schedule a routine appointment, our team is ready to help. We offer free estimates, competitive pricing, and guaranteed satisfaction for all our $kl."),
        ebtn('Get Free Estimate', '/contact/', '#FF6F00'),
    ], $dark);

    $S[] = es1(array_merge([eh2("Why Choose $biz")], benefits_widgets($city)));

    $S[] = es1([
        eh2('What Our Customers Say'),
        ep("Our customers in $city trust us for quality work, honest pricing, and professional service. See what they have to say about their experience with $biz. We are proud of our reputation and work hard to maintain it every day."),
        ehtm('[google_reviews_widget place_id="PLACE_ID"]'),
        ebtn('Read More Reviews', '/reviews/'),
    ]);

    $S[] = es1([
        eh2('How It Works'),
        ep("Getting started with $biz is simple. We have streamlined our process to make it as easy as possible for you to get the professional service you need."),
        eib('fas fa-phone', '1. Contact Us', "Call us at $phone or fill out our online form to request a free estimate. We respond to all inquiries within 24 hours."),
        eib('fas fa-clipboard-check', '2. Free Estimate', 'We visit your property to assess the job, discuss your options, and provide a detailed, no-obligation written estimate.'),
        eib('fas fa-tools', '3. Professional Service', 'Our crew arrives on schedule with all necessary equipment. We complete the work efficiently, safely, and to the highest standards.'),
        eib('fas fa-thumbs-up', '4. Enjoy the Results', 'We clean up the worksite thoroughly, walk you through the completed project, and ensure your total satisfaction before we leave.'),
    ]);

    $S[] = es1([
        eh2('Areas We Serve'),
        ep("We proudly serve $city and the surrounding communities. Our service area includes residential and commercial properties throughout the region."),
        ep("Our service areas include: $areas. Contact us to confirm service availability in your specific location."),
        emap($addr),
        ebtn('Check if We Serve Your Area', '/contact/'),
    ]);

    $S[] = es1([
        eh2('Our Recent Projects'),
        ep("Browse our portfolio of completed projects in $city and surrounding areas. We take pride in the quality of our work and the satisfaction of our customers."),
        eimg(),
        ebtn('View All Projects', '/portfolio/'),
    ]);

    $S[] = es1([
        eh2('Latest from Our Blog'),
        ep("Stay informed with tips, guides, and insights about $kl from our expert team. Our blog covers everything from maintenance advice to industry news and seasonal recommendations."),
        ebtn('Read Our Blog', '/blog/'),
    ]);

    $S[] = es1([
        eh2('Frequently Asked Questions'),
        ep("Find answers to common questions about our $kl in $city. If you do not see your question here, do not hesitate to contact us directly."),
        eacc(home_faq($kw, $biz, $city, $phone, $areas)),
    ]);

    return $S;
}

function build_service_page($svc, $c) {
    $biz = $c['business_name'];
    $city = $c['city'];
    $phone = $c['phone'];
    $state = $c['state'] ?? '';
    $name = $svc['name'];
    $nl = strtolower($name);
    $areas = implode(', ', $c['service_areas'] ?? [$city]);
    $dark = $c['colors']['secondary'] ?? '#1B5E20';
    $accent = $c['colors']['primary'] ?? '#2E7D32';
    $addr = $c['address'] ?? "$city, $state";
    $tel = 'tel:' . preg_replace('/[\(\)\-\s]/', '', $phone);

    $S = [];

    $S[] = es1([
        eh1("$name in $city"),
        ew('text-editor', ['editor' => "<p style='color:#fff;text-align:center;'>Professional $nl services for residential and commercial properties in $city. $biz delivers safe, efficient, and affordable $nl you can trust.</p>"]),
    ], $dark, '120', '120');

    $S[] = es1([
        eh2("Expert $name Services by $biz"),
        ep("When you need $nl done right, trust the experienced professionals at $biz. We combine years of local expertise with modern equipment and proven techniques to deliver outstanding results for every client in $city."),
    ]);

    $S[] = es1([
        ep("$name is a critical service for property owners in $city who want to maintain the safety, health, and appearance of their landscape. Whether you are dealing with an urgent situation or planning routine maintenance, professional $nl ensures the job is done safely and effectively. At $biz, we specialize in providing comprehensive $nl solutions tailored to the unique needs of each property."),
        ep("$city properties face unique challenges that make professional $nl essential. From seasonal weather patterns to local soil conditions and regulations, our team understands the specific factors that affect properties in our area. We have served hundreds of customers throughout $city and the surrounding communities, building a reputation for reliability and fair pricing."),
        ep("Our $nl service covers everything from initial assessment and planning through completion and cleanup. We handle properties of all sizes, from small residential yards to large commercial sites. Our comprehensive approach means you get a complete solution without the need for multiple contractors or follow-up visits."),
    ]);

    $S[] = es1([
        eh2("Is $name Really Necessary?"),
        ep("Many property owners in $city wonder whether they truly need professional $nl. The answer depends on your specific situation, but delaying or avoiding necessary $nl can lead to serious consequences that are far more costly than addressing the issue proactively."),
        eil([
            ['text' => 'Property damage that becomes more expensive to repair over time', 'icon' => 'fas fa-exclamation-triangle'],
            ['text' => 'Safety hazards for your family, visitors, and neighbors', 'icon' => 'fas fa-exclamation-triangle'],
            ['text' => 'Decreased property value and curb appeal', 'icon' => 'fas fa-exclamation-triangle'],
            ['text' => 'Potential liability if problems affect neighboring properties', 'icon' => 'fas fa-exclamation-triangle'],
            ['text' => "Violation of local codes and regulations in $city", 'icon' => 'fas fa-exclamation-triangle'],
        ]),
        ep("We recommend contacting a professional for an assessment if you notice any concerning signs. Early intervention is almost always less expensive and less disruptive than waiting for a problem to escalate. Our team offers free evaluations to help you determine the best course of action."),
    ]);

    $S[] = es1([
        eh2("What Is Included in Our $name Service"),
        eib('fas fa-clipboard-check', 'Initial Assessment & Planning', "Every $nl project begins with a thorough assessment of your property. Our experienced professionals evaluate the situation, identify potential challenges, and develop a detailed plan to ensure safe and efficient completion."),
        eib('fas fa-tools', 'Professional Execution', "Our trained crew arrives with all necessary equipment and materials. We follow industry best practices and safety protocols throughout the process, working efficiently while maintaining the highest quality standards."),
        eib('fas fa-broom', 'Complete Cleanup', "We do not consider a job finished until the worksite is thoroughly cleaned. Our team removes all debris, materials, and equipment from your property, leaving your yard looking better than we found it."),
        eib('fas fa-search', 'Final Inspection & Walkthrough', "After completing the work and cleanup, we conduct a final inspection. We walk you through the completed project, answer any questions, and make sure you are completely satisfied with the results."),
        ep("From start to finish, our $nl process is designed to be transparent, efficient, and stress-free. We keep you informed at every step and adjust our approach as needed for the best possible outcome."),
    ]);

    $S[] = es1([
        eh2("How $name is Performed Safely"),
        ep("Safety is our top priority on every $nl project. $biz follows strict safety protocols that meet or exceed industry standards. Our comprehensive approach protects your property, our team, and your community."),
        eib('fas fa-certificate', 'Certified Professionals', "All our team members are trained and certified in safe $nl practices. We maintain current certifications and participate in ongoing training to stay at the forefront of safety standards."),
        eib('fas fa-hard-hat', 'Proper Equipment & Techniques', "We use professional-grade equipment that is regularly inspected and maintained. Our techniques follow industry best practices to minimize risk to your property and surroundings."),
        eib('fas fa-shield-alt', 'Property Protection', "Before starting any work, we take precautions to protect your property including ground protection, barrier installation, and careful planning to prevent damage to structures and landscaping."),
        ep("We comply with all local regulations and safety requirements in $city. Our team carries proper insurance and follows OSHA guidelines to ensure a safe working environment on every job site."),
    ]);

    $S[] = es1([
        eh2("$name Pricing in $city"),
        ep("The cost of $nl in $city depends on several factors including the scope of work, accessibility, and project complexity. Below are our general pricing guidelines. For an exact quote tailored to your specific needs, contact us for a free estimate."),
        ew('text-editor', ['editor' => "<table style='width:100%;border-collapse:collapse;'><tr style='background:$accent;color:#fff;'><th style='padding:12px;border:1px solid #ddd;'>Package</th><th style='padding:12px;border:1px solid #ddd;'>Price Range</th><th style='padding:12px;border:1px solid #ddd;'>Includes</th></tr><tr><td style='padding:12px;border:1px solid #ddd;font-weight:bold;'>Basic</td><td style='padding:12px;border:1px solid #ddd;'>Starting at \$199</td><td style='padding:12px;border:1px solid #ddd;'>Standard service, professional crew, same-day cleanup</td></tr><tr style='background:#f9f9f9;'><td style='padding:12px;border:1px solid #ddd;font-weight:bold;'>Standard</td><td style='padding:12px;border:1px solid #ddd;'>Starting at \$499</td><td style='padding:12px;border:1px solid #ddd;'>Comprehensive service, professional crew, cleanup, 1-year warranty</td></tr><tr><td style='padding:12px;border:1px solid #ddd;font-weight:bold;'>Premium</td><td style='padding:12px;border:1px solid #ddd;'>Starting at \$999</td><td style='padding:12px;border:1px solid #ddd;'>Full-service package, senior crew, priority scheduling, extended warranty, follow-up</td></tr></table>"]),
        ep("Additional factors that may affect pricing include emergency or same-day service, difficult access conditions, special equipment requirements, and seasonal demand. We always provide a detailed written estimate before beginning any work so there are no surprises."),
        ebtn('Get Custom Quote', '/contact/', '#FF6F00'),
    ]);

    $S[] = es1([
        eh2("Professional $name vs DIY"),
        ew('text-editor', ['editor' => "<table style='width:100%;border-collapse:collapse;'><tr style='background:#f5f5f5;'><th style='padding:12px;border:1px solid #ddd;'>Factor</th><th style='padding:12px;border:1px solid #ddd;'>Professional $name</th><th style='padding:12px;border:1px solid #ddd;'>DIY Approach</th></tr><tr><td style='padding:12px;border:1px solid #ddd;'>Safety</td><td style='padding:12px;border:1px solid #ddd;'>Trained professionals with safety equipment</td><td style='padding:12px;border:1px solid #ddd;'>High risk without proper training</td></tr><tr style='background:#f9f9f9;'><td style='padding:12px;border:1px solid #ddd;'>Equipment</td><td style='padding:12px;border:1px solid #ddd;'>Professional-grade tools included</td><td style='padding:12px;border:1px solid #ddd;'>Expensive to rent, hard to operate</td></tr><tr><td style='padding:12px;border:1px solid #ddd;'>Insurance</td><td style='padding:12px;border:1px solid #ddd;'>Fully insured, liability covered</td><td style='padding:12px;border:1px solid #ddd;'>No coverage for property damage</td></tr><tr style='background:#f9f9f9;'><td style='padding:12px;border:1px solid #ddd;'>Results</td><td style='padding:12px;border:1px solid #ddd;'>Guaranteed quality with warranty</td><td style='padding:12px;border:1px solid #ddd;'>Uncertain quality, no warranty</td></tr></table>"]),
        ep("While DIY $nl may seem like a cost-saving option, the risks and potential for costly mistakes often outweigh any savings. Professional $nl from $biz provides peace of mind, guaranteed results, and protection through our comprehensive insurance and warranty coverage."),
    ]);

    $S[] = es1([
        eh2('Trust, Security & Compliance'),
        eib('fas fa-certificate', 'Licensed & Certified', "Our team holds all required licenses and certifications for $nl in $city and $state. We maintain current credentials and pursue continuing education."),
        eib('fas fa-file-invoice-dollar', 'Insurance & Bonded', "We carry comprehensive general liability insurance and workers compensation coverage, protecting your property and providing peace of mind."),
        eib('fas fa-handshake', 'Warranty & Guarantees', "All our $nl work comes with a written warranty. If any issues arise after project completion, we return to address them at no additional cost."),
        eib('fas fa-user-check', 'Background Checked', "Every team member undergoes a thorough background check before joining $biz. We prioritize your safety and security on every job."),
        ep("We comply with all local building codes, environmental regulations, and safety requirements in $city. Our operations are fully transparent and we provide documentation upon request."),
    ]);

    $S[] = es1([
        eh2("Why Choose $biz"),
        ep("$biz is not just another $nl company. We are your neighbors, committed to maintaining the safety and beauty of $city properties. Here is what sets us apart from the competition."),
        eib('fas fa-map-marker-alt', "Deep $city Expertise", "We have served $city for over a decade, handling thousands of $nl projects. This local experience means we understand the specific challenges properties face in our area."),
        eib('fas fa-dollar-sign', 'Transparent Pricing', "We provide detailed written estimates with no hidden fees. Our pricing is competitive and fair, and we explain every line item so you know exactly what you are paying for."),
        eib('fas fa-heart', 'Customer-First Approach', "Our business is built on referrals and repeat customers. We treat every property as if it were our own, communicating clearly and ensuring your complete satisfaction."),
        eib('fas fa-cog', 'Modern Equipment', "We invest in the latest professional equipment to provide efficient, safe, and high-quality $nl. Our well-maintained fleet ensures we are prepared for projects of any size."),
        ep("Since our founding, $biz has grown from a small local operation to $city's most trusted $nl service. Our success is built on great work, fair treatment, and standing behind our results."),
    ]);

    $S[] = es1([
        eh2('Industry Standards & Best Practices'),
        ep("Our $nl services follow the standards set by leading industry organizations. We stay current with evolving best practices and safety guidelines to ensure our clients receive the highest quality service available."),
        ep("We adhere to all applicable OSHA safety regulations, local building codes, and environmental protection guidelines. Our commitment to industry standards means you can trust that the work performed on your property meets the highest professional benchmarks."),
    ]);

    $S[] = es1([
        eh2('Areas We Serve'),
        ep("We provide professional $nl services throughout $city and the surrounding communities. Our service area covers both residential and commercial properties in the greater $city region."),
        ep("Our service areas include: $areas. If your location is not listed, give us a call at $phone to confirm availability."),
        emap($addr),
        ebtn('Check if We Serve Your Area', '/contact/'),
    ]);

    $S[] = es1([
        eh2('Ready to Get Started?'),
        ep("Choose $biz for your $nl needs and experience the difference that professional service makes. With our licensed team, comprehensive insurance, written warranty, and commitment to satisfaction, your property is in the best hands."),
        ep("Getting started is easy. Call us or fill out our contact form for a free, no-obligation estimate. One of our experienced professionals will visit your property, assess the situation, and provide a detailed quote."),
        ebtn('Get Free Quote', '/contact/', '#FF6F00'),
        ebtn("Call Now $phone", $tel, $accent),
        ep('We respond to all inquiries within 24 hours. For emergencies, call us directly for immediate assistance.'),
    ], $dark);

    $S[] = es1([
        eh2("Frequently Asked Questions About $name"),
        eacc($svc['faq'] ?? svc_faq($name, $biz, $city, $phone)),
    ]);

    return $S;
}

function build_about_page($c) {
    $biz = $c['business_name'];
    $city = $c['city'];
    $phone = $c['phone'];
    $kw = $c['main_keyword'] ?? 'services';
    $kl = strtolower($kw);
    $areas = implode(', ', $c['service_areas'] ?? [$city]);
    $dark = $c['colors']['secondary'] ?? '#1B5E20';
    $addr = $c['address'] ?? "$city, " . ($c['state'] ?? '');
    $tel = 'tel:' . preg_replace('/[\(\)\-\s]/', '', $phone);

    return [
        es1([eh1("About $biz"), ew('text-editor', ['editor' => "<p style='color:#fff;text-align:center;'>Your trusted partner for professional $kl in $city and surrounding areas.</p>"])], $dark, '120', '120'),
        es1([eh2('Our Story'), ep("$biz was founded with a simple mission: to provide the highest quality $kl to homeowners and businesses in $city. What started as a small operation has grown into one of the most trusted names in the industry throughout the region."), ep("Over the years, we have built our reputation one satisfied customer at a time. Our commitment to quality workmanship, honest pricing, and exceptional customer service has earned us the trust of thousands of property owners in $city and the surrounding communities."), ep("Today, $biz continues to grow while maintaining the personal touch and attention to detail that our customers expect. We treat every project, big or small, with the same level of professionalism and care that built our reputation.")]),
        es1([eh2('Our Team'), ep("Our team consists of experienced, licensed, and certified professionals who are passionate about their work. Each team member undergoes extensive training and background checks before joining $biz."), eib('fas fa-graduation-cap', 'Trained & Certified', 'Every team member holds current certifications and participates in ongoing training to stay updated on industry best practices and safety standards.'), eib('fas fa-user-check', 'Background Verified', "We conduct thorough background checks on all employees. Your safety and trust are our top priorities on every single project."), eib('fas fa-users', 'Experienced Professionals', "Our team brings years of combined experience in $kl, giving us the expertise to handle any project with confidence and precision.")]),
        es1(array_merge([eh2("Why Choose $biz")], benefits_widgets($city))),
        es1([eh2('Our Service Area'), ep("We proudly serve $city and the surrounding communities including $areas. Our local knowledge allows us to provide tailored solutions for properties throughout the region."), emap($addr)]),
        es1([eh2('Ready to Work With Us?'), ep("Contact $biz today for a free, no-obligation estimate. We look forward to serving you and showing you why we are $city's preferred choice for $kl."), ebtn("Call Now $phone", $tel, '#FF6F00')], $dark),
    ];
}

function build_contact_page($c) {
    $biz = $c['business_name'];
    $city = $c['city'];
    $phone = $c['phone'];
    $email = $c['email'] ?? $c['admin_email'] ?? '';
    $addr = $c['address'] ?? "$city, " . ($c['state'] ?? '');
    $dark = $c['colors']['secondary'] ?? '#1B5E20';
    $tel = 'tel:' . preg_replace('/[\(\)\-\s]/', '', $phone);

    return [
        es1([eh1("Contact $biz")], $dark, '120', '120'),
        es1([eh2('Get in Touch'), eil([['text' => "Phone: $phone", 'icon' => 'fas fa-phone'], ['text' => "Email: $email", 'icon' => 'fas fa-envelope'], ['text' => "Address: $addr", 'icon' => 'fas fa-map-marker-alt'], ['text' => 'Hours: Monday - Saturday, 7:00 AM - 6:00 PM', 'icon' => 'fas fa-clock']]), ep("Call us today at $phone for a free estimate. We respond to all inquiries within 24 hours and offer flexible scheduling to fit your needs.")]),
        es1([eh2('Find Us'), ep("We are conveniently located to serve $city and surrounding areas. Visit us or schedule an on-site estimate at your property."), emap($addr)]),
        es1([eh2("Why Contact $biz?"), ep("When you reach out to $biz, you are connecting with a team that genuinely cares about your property and satisfaction."), eib('fas fa-clock', 'Fast Response', 'We respond to all inquiries within 24 hours and offer same-day estimates when possible.'), eib('fas fa-dollar-sign', 'Free Estimates', 'Every estimate is completely free with no obligation. We provide transparent, detailed pricing.'), eib('fas fa-star', '5-Star Service', "Our customers in $city consistently rate us 5 stars for quality, reliability, and professionalism.")]),
        es1([eh2('Call Us Now'), ebtn("Call $phone", $tel, '#FF6F00')], $dark),
    ];
}

function build_services_page($c) {
    $biz = $c['business_name'];
    $city = $c['city'];
    $kw = $c['main_keyword'] ?? 'services';
    $kl = strtolower($kw);
    $svcs = $c['services'] ?? [];
    $dark = $c['colors']['secondary'] ?? '#1B5E20';

    $svc_w = [eh2("Professional $kw in $city")];
    foreach ($svcs as $svc) {
        $desc = $svc['description'] ?? "Professional " . strtolower($svc['name']) . " services for residential and commercial properties. Click to learn more.";
        $svc_w[] = eib('fas fa-leaf', $svc['name'], $desc);
    }

    return [
        es1([eh1("Our $kw Services"), ew('text-editor', ['editor' => "<p style='color:#fff;text-align:center;'>$biz offers a comprehensive range of professional $kl for residential and commercial properties in $city.</p>"])], $dark, '120', '120'),
        es1($svc_w),
        es1(array_merge([eh2("Why Choose $biz")], benefits_widgets($city))),
        es1([eh2('Ready to Get Started?'), ep("Contact us today for a free estimate on any of our $kl."), ebtn('Get Free Estimate', '/contact/', '#FF6F00')], $dark),
    ];
}

function build_terms_page($c) {
    $biz = $c['business_name'];
    $city = $c['city'];
    $state = $c['state'] ?? '';
    $email = $c['email'] ?? $c['admin_email'] ?? '';
    $dark = $c['colors']['secondary'] ?? '#1B5E20';
    $now = date('F Y');

    $terms = "<h3>1. Agreement to Terms</h3><p>By accessing or using the services provided by $biz (Company), you agree to be bound by these Terms of Service. If you do not agree, please do not use our services.</p><h3>2. Services</h3><p>$biz provides professional services as described on our website and in project estimates. All services are subject to availability and may vary based on your specific requirements and property conditions.</p><h3>3. Estimates and Pricing</h3><p>All estimates provided are based on our assessment at the time of inspection. Final pricing may vary if additional work is discovered or requested. Changes to the scope of work will be communicated and approved before proceeding.</p><h3>4. Payment Terms</h3><p>Payment is due upon completion of services unless otherwise agreed in writing. We accept cash, checks, and major credit cards. For projects exceeding \$1,000, a deposit may be required.</p><h3>5. Warranties</h3><p>All work performed by $biz is covered by our written warranty as specified in your service agreement. Normal wear and tear, acts of nature, and damage caused by third parties are excluded from warranty coverage.</p><h3>6. Liability</h3><p>$biz carries comprehensive general liability and workers compensation insurance. Our liability is limited to the scope of services provided and the amount paid for those services.</p><h3>7. Cancellations</h3><p>Cancellations made at least 48 hours before the scheduled service date will receive a full refund of any deposit. Late cancellations may be subject to a cancellation fee.</p><h3>8. Property Access</h3><p>By scheduling services, you grant $biz permission to access your property as necessary to perform the agreed-upon work. You are responsible for informing us of any hazards or access restrictions.</p><h3>9. Governing Law</h3><p>These Terms are governed by the laws of the State of $state. Any disputes will be resolved in the appropriate courts of $city, $state.</p><h3>10. Contact</h3><p>For questions about these Terms, contact us at $email.</p><p><em>Last updated: $now</em></p>";

    return [
        es1([eh1('Terms of Service')], $dark, '120', '120'),
        es1([ew('text-editor', ['editor' => $terms])]),
        es1([ebtn('Back to Home', '/')], $dark),
    ];
}

function build_privacy_page($c) {
    $biz = $c['business_name'];
    $email = $c['email'] ?? $c['admin_email'] ?? '';
    $dark = $c['colors']['secondary'] ?? '#1B5E20';
    $now = date('F Y');

    $privacy = "<h3>1. Information We Collect</h3><p>$biz collects information that you provide directly to us, including your name, phone number, email address, property address, and details about your service needs. We also collect information automatically when you visit our website.</p><h3>2. How We Use Your Information</h3><p>We use the information we collect to provide and improve our services, communicate with you about estimates and projects, send important service updates, and respond to your inquiries. We do not sell your personal information to third parties.</p><h3>3. Information Sharing</h3><p>We may share your information with trusted service providers who assist us in operating our business, such as payment processors and scheduling software. These parties are required to keep your information confidential.</p><h3>4. Data Security</h3><p>$biz takes reasonable measures to protect your personal information from unauthorized access, disclosure, or destruction. However, no method of electronic transmission or storage is completely secure.</p><h3>5. Cookies and Tracking</h3><p>Our website may use cookies and similar tracking technologies to improve your browsing experience and analyze website traffic. You can control cookies through your browser settings.</p><h3>6. Your Rights</h3><p>You have the right to access, correct, or delete your personal information. You may also opt out of marketing communications at any time by contacting us at $email.</p><h3>7. Changes to This Policy</h3><p>We may update this Privacy Policy from time to time. We will notify you of material changes by posting the updated policy on our website.</p><h3>8. Contact Us</h3><p>If you have questions about this Privacy Policy, contact $biz at $email.</p><p><em>Last updated: $now</em></p>";

    return [
        es1([eh1('Privacy Policy')], $dark, '120', '120'),
        es1([ew('text-editor', ['editor' => $privacy])]),
        es1([ebtn('Back to Home', '/')], $dark),
    ];
}

$c = $input;
$results = [];
$errors = [];

$elementor_ok = install_wp_plugin('elementor');
if (!$elementor_ok) $errors[] = 'Failed to install Elementor';

$theme_ok = install_wp_theme('hello-elementor');
if (!$theme_ok) $errors[] = 'Failed to install Hello Elementor theme';

if (!empty($errors)) {
    http_response_code(500);
    die(json_encode(['success' => false, 'errors' => $errors]));
}

$existing = get_posts(['post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any']);
foreach ($existing as $pg) {
    wp_delete_post($pg->ID, true);
}

$existing_kit = get_posts(['post_type' => 'elementor_library', 'numberposts' => -1, 'post_status' => 'any']);
foreach ($existing_kit as $k) {
    wp_delete_post($k->ID, true);
}

$pages = [];
$pages[] = ['title' => 'Home', 'slug' => 'home', 'data' => build_homepage($c)];
$pages[] = ['title' => 'About Us', 'slug' => 'about-us', 'data' => build_about_page($c)];
$pages[] = ['title' => 'Contact Us', 'slug' => 'contact', 'data' => build_contact_page($c)];
$pages[] = ['title' => 'Services', 'slug' => 'services', 'data' => build_services_page($c)];

foreach ($c['services'] ?? [] as $svc) {
    $slug = $svc['slug'] ?? sanitize_title($svc['name']);
    $pages[] = ['title' => $svc['name'], 'slug' => $slug, 'data' => build_service_page($svc, $c)];
}

$pages[] = ['title' => 'Terms of Service', 'slug' => 'terms-of-service', 'data' => build_terms_page($c)];
$pages[] = ['title' => 'Privacy Policy', 'slug' => 'privacy-policy', 'data' => build_privacy_page($c)];

$created = [];
$home_id = 0;

foreach ($pages as $pg) {
    $post_id = wp_insert_post([
        'post_title' => $pg['title'],
        'post_name' => $pg['slug'],
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_author' => 1,
    ]);

    if (is_wp_error($post_id)) {
        $errors[] = "Failed to create page: {$pg['title']}";
        continue;
    }

    $json_data = wp_json_encode($pg['data']);
    update_post_meta($post_id, '_elementor_data', wp_slash($json_data));
    update_post_meta($post_id, '_elementor_edit_mode', 'builder');
    update_post_meta($post_id, '_elementor_version', '3.25.0');
    update_post_meta($post_id, '_wp_page_template', 'elementor_canvas');

    if ($pg['slug'] === 'home') $home_id = $post_id;

    $domain = parse_url(home_url(), PHP_URL_HOST);
    $url = home_url("/{$pg['slug']}/");
    if ($pg['slug'] === 'home') $url = home_url('/');
    $created[] = ['id' => $post_id, 'title' => $pg['title'], 'url' => $url];
}

update_option('blogname', $c['business_name']);
update_option('blogdescription', $c['tagline'] ?? '');
update_option('show_on_front', 'page');
update_option('page_on_front', $home_id);
update_option('permalink_structure', '/%postname%/');

$primary = $c['colors']['primary'] ?? '#2E7D32';
$secondary = $c['colors']['secondary'] ?? '#1B5E20';
$accent = $c['colors']['accent'] ?? '#FF6F00';

$kit_id = wp_insert_post([
    'post_title' => 'Default Kit',
    'post_name' => 'default-kit',
    'post_status' => 'publish',
    'post_type' => 'elementor_library',
    'post_author' => 1,
]);

if (!is_wp_error($kit_id)) {
    update_post_meta($kit_id, '_elementor_template_type', 'kit');
    update_post_meta($kit_id, '_elementor_edit_mode', 'builder');
    update_post_meta($kit_id, '_elementor_version', '3.25.0');
    update_post_meta($kit_id, '_elementor_data', '[]');

    $kit_settings = [
        'system_colors' => [
            ['_id' => 'primary', 'title' => 'Primary', 'color' => $primary],
            ['_id' => 'secondary', 'title' => 'Secondary', 'color' => $secondary],
            ['_id' => 'text', 'title' => 'Text', 'color' => '#3E4751'],
            ['_id' => 'accent', 'title' => 'Accent', 'color' => $accent],
        ],
        'system_typography' => [
            ['_id' => 'primary', 'title' => 'Primary', 'typography_typography' => 'custom', 'typography_font_family' => 'Inter', 'typography_font_weight' => '700'],
            ['_id' => 'secondary', 'title' => 'Secondary', 'typography_typography' => 'custom', 'typography_font_family' => 'Inter', 'typography_font_weight' => '600'],
            ['_id' => 'text', 'title' => 'Text', 'typography_typography' => 'custom', 'typography_font_family' => 'Inter', 'typography_font_weight' => '400'],
        ],
        'container_width' => ['unit' => 'px', 'size' => 1140, 'sizes' => (object)[]],
        'page_title_selector' => 'h1.entry-title',
        'body_color' => '#3E4751',
        'h1_color' => $secondary,
        'h2_color' => $secondary,
        'h3_color' => $secondary,
    ];
    update_post_meta($kit_id, '_elementor_page_settings', $kit_settings);
    update_option('elementor_active_kit', $kit_id);
}

update_option('elementor_disable_color_schemes', 'yes');
update_option('elementor_disable_typography_schemes', 'yes');
update_option('elementor_default_generic_fonts', 'Sans-serif');
update_option('elementor_container_width', '1140');
update_option('elementor_space_between_widgets', '20');
update_option('elementor_page_title_selector', 'h1.entry-title');
update_option('elementor_experiment-container', 'active');

flush_rewrite_rules();

@unlink(__FILE__);

wp_send_json([
    'success' => empty($errors),
    'pages_created' => count($created),
    'pages' => $created,
    'homepage' => home_url('/'),
    'errors' => $errors,
]);
