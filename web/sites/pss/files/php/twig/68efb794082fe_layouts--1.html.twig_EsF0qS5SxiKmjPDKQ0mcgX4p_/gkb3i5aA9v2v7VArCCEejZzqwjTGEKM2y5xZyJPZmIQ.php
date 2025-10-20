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

/* modules/contrib/dashboards/templates/layouts/layouts--1.html.twig */
class __TwigTemplate_8953d414570b9d4c2c4058d23fa0f7a9 extends Template
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
        // line 1
        yield "<section ";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["attributes"] ?? null), "addClass", ["layouts-dashboards-1", "layouts-dashboards"], "method", false, false, true, 1), "html", null, true);
        yield ">
  <div class=\"drow\">
    ";
        // line 3
        if (CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "one", [], "any", false, false, true, 3)) {
            // line 4
            yield "    <div ";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["region_attributes"] ?? null), "one", [], "any", false, false, true, 4), "html", null, true);
            yield ">
      ";
            // line 5
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "one", [], "any", false, false, true, 5), "html", null, true);
            yield "
    </div>
    ";
        }
        // line 8
        yield "    ";
        if (CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "two", [], "any", false, false, true, 8)) {
            // line 9
            yield "    <div ";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["region_attributes"] ?? null), "two", [], "any", false, false, true, 9), "html", null, true);
            yield ">
      ";
            // line 10
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "two", [], "any", false, false, true, 10), "html", null, true);
            yield "
    </div>
    ";
        }
        // line 13
        yield "  </div>
  ";
        // line 14
        if (CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "three", [], "any", false, false, true, 14)) {
            // line 15
            yield "  <div class=\"drow\">
    <div ";
            // line 16
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["region_attributes"] ?? null), "three", [], "any", false, false, true, 16), "html", null, true);
            yield ">
      ";
            // line 17
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "three", [], "any", false, false, true, 17), "html", null, true);
            yield "
    </div>
  </div>
  ";
        }
        // line 21
        yield "</section>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["attributes", "content", "region_attributes"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "modules/contrib/dashboards/templates/layouts/layouts--1.html.twig";
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
        return array (  96 => 21,  89 => 17,  85 => 16,  82 => 15,  80 => 14,  77 => 13,  71 => 10,  66 => 9,  63 => 8,  57 => 5,  52 => 4,  50 => 3,  44 => 1,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "modules/contrib/dashboards/templates/layouts/layouts--1.html.twig", "/var/www/html/web/modules/contrib/dashboards/templates/layouts/layouts--1.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 3];
        static $filters = ["escape" => 1];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                ['if'],
                ['escape'],
                [],
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
