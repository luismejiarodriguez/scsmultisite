<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/* @help_topics/statistics.tracking_popular_content.html.twig */
class __TwigTemplate_d2ff6bdd2547ce1d6fec0077a9d1171b extends Template
{
    private Source $source;
    /**
     * @var array<string, Template>
     */
    private array $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
        $this->sandbox = $this->extensions[SandboxExtension::class];
        $this->checkSecurity();
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 8
        $context["statistics_settings_link_text"] = ('' === $tmp = \Twig\Extension\CoreExtension::captureOutput((function () use (&$context, $macros, $blocks) {
            yield t("Statistics", array());
            yield from [];
        })())) ? '' : new Markup($tmp, $this->env->getCharset());
        // line 9
        $context["permissions_link_text"] = ('' === $tmp = \Twig\Extension\CoreExtension::captureOutput((function () use (&$context, $macros, $blocks) {
            yield t("Permissions", array());
            yield from [];
        })())) ? '' : new Markup($tmp, $this->env->getCharset());
        // line 10
        $context["statistics_settings_link"] = $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\help\HelpTwigExtension']->getRouteLink(($context["statistics_settings_link_text"] ?? null), "statistics.settings"));
        // line 11
        $context["permissions_link"] = $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\help\HelpTwigExtension']->getRouteLink(($context["permissions_link_text"] ?? null), "user.admin_permissions"));
        // line 12
        yield "<h2>";
        yield t("Goal", array());
        yield "</h2>
<p>";
        // line 13
        yield t("Configure and display tracking of how many times content has been viewed on your site, assuming that the core Statistics module is currently installed.", array());
        yield "</p>
<h2>";
        // line 14
        yield t("What are the options for displaying popularity tracking?", array());
        yield "</h2>
<p>";
        // line 15
        yield t("You can display a <em>content hits</em> counter of how many times a content item has been viewed, at the bottom of content item pages. You can also place a <em>Popular content</em> block in a region of your theme, which shows a list of the most popular and most recently-viewed content.", array());
        yield "</p>
<h2>";
        // line 16
        yield t("Steps", array());
        yield "</h2>
<ol>
  <li>";
        // line 18
        yield t("In the <em>Manage</em> administrative menu, navigate to <em>Configuration</em> &gt; <em>System</em> &gt; <em>@statistics_settings_link</em>.", array("@statistics_settings_link" => ($context["statistics_settings_link"] ?? null), ));
        yield "</li>
  <li>";
        // line 19
        yield t("Check <em>Count content views</em> and click <em>Save configuration</em>.", array());
        yield "</li>
  <li>";
        // line 20
        yield t("In the <em>Manage</em> administrative menu, navigate to <em>People</em> &gt; <em>@permissions_link</em>.", array("@permissions_link" => ($context["permissions_link"] ?? null), ));
        yield "</li>
  <li>";
        // line 21
        yield t("In the <em>Statistics</em> section, check or uncheck the <em>View content hits</em> permission for each role. Click <em>Save permissions</em>.", array());
        yield "</li>
  <li>";
        // line 22
        yield t("Optionally, in the <em>Manage</em> administrative menu, navigate to <em>Structure</em> &gt; <em>Block layout</em>. Place the <em>Popular content</em> block in a region in your theme (you will need to have the core Block module installed; see related topic for more details on block placement).", array());
        yield "</li>
</ol>";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "@help_topics/statistics.tracking_popular_content.html.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable(): bool
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  96 => 22,  92 => 21,  88 => 20,  84 => 19,  80 => 18,  75 => 16,  71 => 15,  67 => 14,  63 => 13,  58 => 12,  56 => 11,  54 => 10,  49 => 9,  44 => 8,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "@help_topics/statistics.tracking_popular_content.html.twig", "/var/www/html/web/core/modules/statistics/help_topics/statistics.tracking_popular_content.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["set" => 8, "trans" => 8];
        static $filters = ["escape" => 18];
        static $functions = ["render_var" => 10, "help_route_link" => 10];

        try {
            $this->sandbox->checkSecurity(
                ['set', 'trans'],
                ['escape'],
                ['render_var', 'help_route_link'],
                $this->source
            );
        } catch (SecurityError $e) {
            $e->setSourceContext($this->source);

            if ($e instanceof SecurityNotAllowedTagError && isset($tags[$e->getTagName()])) {
                $e->setTemplateLine($tags[$e->getTagName()]);
            } elseif ($e instanceof SecurityNotAllowedFilterError && isset($filters[$e->getFilterName()])) {
                $e->setTemplateLine($filters[$e->getFilterName()]);
            } elseif ($e instanceof SecurityNotAllowedFunctionError && isset($functions[$e->getFunctionName()])) {
                $e->setTemplateLine($functions[$e->getFunctionName()]);
            }

            throw $e;
        }

    }
}
