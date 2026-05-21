<?php

namespace MediaWiki\Extension\DisplayTitle;

use HtmlArmor;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\OutputPageParserOutputHook;
use MediaWiki\Hook\ParserAfterParseHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\SelfLinkBeginHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Linker\Hook\HtmlPageLinkRendererBeginHook;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\Hook\RevisionDataUpdatesHook;
use NamespaceInfo;
use OutputPage;
use Parser;
use ParserOutput;
use RequestContext;
use Skin;
use SkinTemplate;
use StripState;
use Title;

class DisplayTitleHooks implements
	ParserFirstCallInitHook,
	ParserAfterParseHook,
	BeforePageDisplayHook,
	HtmlPageLinkRendererBeginHook,
	OutputPageParserOutputHook,
	SelfLinkBeginHook,
	SkinTemplateNavigation__UniversalHook,
	RevisionDataUpdatesHook
{
	/**
	 * @var DisplayTitleService
	 */
	private $displayTitleService;

	/**
	 * @var NamespaceInfo
	 */
	private $namespaceInfo;

	/**
	 * @param DisplayTitleService $displayTitleService
	 * @param NamespaceInfo $namespaceInfo
	 */
	public function __construct(
		DisplayTitleService $displayTitleService,
		NamespaceInfo $namespaceInfo
	) {
		$this->displayTitleService = $displayTitleService;
		$this->namespaceInfo = $namespaceInfo;
	}

	/**
	 * Implements ParserFirstCallInit hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * @since 1.0
	 * @param Parser $parser the Parser object
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook(
			'getdisplaytitle',
			[ $this, 'getdisplaytitleParserFunction' ]
		);
	}

	/**
	 * Handle #getdisplaytitle parser function.
	 *
	 * @since 1.0
	 * @param Parser $parser the Parser object
	 * @param string $pagename the name of the page
	 * @return string the displaytitle of the page; defaults to pagename if
	 * displaytitle is not set
	 */
	public function getdisplaytitleParserFunction( Parser $parser, string $pagename ): string {
		$title = Title::newFromText( $pagename );
		$originalPagename = $pagename;
		if ( $title !== null ) {
			$this->displayTitleService->getDisplayTitle( $title, $pagename );
		}
		if ( $pagename === null ) {
			return $originalPagename;
		}
		return $pagename;
	}

	/**
	 * Implements ParserAfterParse hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/ParserAfterParse
	 * Detects display title changes to trigger a purge of incoming links.
	 *
	 * @since 1.6
	 * @param Parser $parser the Parser object
	 * @param string &$text the parser output text
	 * @param StripState $stripState the StripState object
	 */
	public function onParserAfterParse( $parser, &$text, $stripState ): void {
		$parserTitle = $parser->getTitle();
		$oldDisplayTitle = '';
		$this->displayTitleService->getDisplayTitle( $parserTitle, $oldDisplayTitle );
		$newDisplayTitle = $parser->getOutput()->getDisplayTitle();

		if ( $oldDisplayTitle !== $newDisplayTitle ) {
			$parser->getOutput()->setExtensionData( 'displayTitle', [
				'displayTitleChanged' => true
			] );
		}
	}

	/**
	 * Implements RevisionDataUpdates hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/RevisionDataUpdates
	 * Pushes a purge job for incoming links when the display title changes.
	 *
	 * @since 1.6
	 * @param Title $title the Title of the page being saved
	 * @param \MediaWiki\Revision\RenderedRevision $renderedRevision the rendered revision
	 * @param \DeferrableUpdate[] &$updates list of DeferrableUpdate objects
	 */
	public function onRevisionDataUpdates( $title, $renderedRevision, &$updates ): void {
		$output = $renderedRevision->getRevisionParserOutput();
		$displayTitleData = $output->getExtensionData( 'displayTitle' );

		if ( $displayTitleData !== null && !empty( $displayTitleData['displayTitleChanged'] ) ) {
			MediaWikiServices::getInstance()->getJobQueueGroup()->lazyPush(
				new DisplayTitlePurgeIncomingLinksJob( [ 'pageid' => $title->getId() ] )
			);
		}
	}

	// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

	/**
	 * Implements SkinTemplateNavigation::Universal hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation::Universal
	 *
	 * Migrated from PersonalUrls hook, see https://phabricator.wikimedia.org/T319087
	 *
	 * @since 1.5
	 * @param SkinTemplate $sktemplate SkinTemplate object providing context
	 * @param array &$links The array of arrays of URLs set up so far
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		$pagename = null;
		if ( $sktemplate->getUser()->isRegistered() ) {
			$menu_urls = $links['user-menu'] ?? [];
			if ( isset( $menu_urls['userpage'] ) ) {
				$pagename = $menu_urls['userpage']['text'];
				$title = $sktemplate->getUser()->getUserPage();
				$this->displayTitleService->getDisplayTitle( $title, $pagename );
				$links['user-menu']['userpage']['text'] = $pagename;
			}
			$page_urls = $links['user-page'] ?? [];
			if ( isset( $page_urls['userpage'] ) ) {
				// If we determined $pagename already, don't do so again.
				if ( $pagename === null ) {
					$pagename = $page_urls['userpage']['text'];
					$title = $sktemplate->getUser()->getUserPage();
					$this->displayTitleService->getDisplayTitle( $title, $pagename );
				}
				$links['user-page']['userpage']['text'] = $pagename;
			}
		}
	}

	// phpcs:enable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

	/**
	 * Implements HtmlPageLinkRendererBegin hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/HtmlPageLinkRendererBegin
	 * Handle links to other pages.
	 *
	 * @since 1.4
	 * @param LinkRenderer $linkRenderer the LinkRenderer object
	 * @param LinkTarget $target the LinkTarget that the link is pointing to
	 * @param string|null|HtmlArmor &$text the contents that the <a> tag should have
	 * @param string[] &$customAttribs the HTML attributes that the <a> tag should have
	 * @param string[] &$query the query string to add to the generated URL
	 * @param string &$ret the value to return if the hook returns false
	 */
	public function onHtmlPageLinkRendererBegin( $linkRenderer, $target, &$text, &$customAttribs, &$query, &$ret ) {
		if ( RequestContext::getMain()->hasTitle() ) {
			$title = RequestContext::getMain()->getTitle();
			$this->displayTitleService->handleLink(
				$title->getPrefixedText(),
				Title::newFromLinkTarget( $target ),
				$text,
				true
			);
		}
	}

	/**
	 * Implements SelfLinkBegin hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/SelfLinkBegin
	 * Handle self links.
	 *
	 * @since 1.3
	 * @param Title $nt the Title object of the page
	 * @param string &$html the HTML of the link text
	 * @param string &$trail Text after link
	 * @param string &$prefix Text before link
	 * @param string &$ret the value to return if the hook returns false
	 */
	public function onSelfLinkBegin( $nt, &$html, &$trail, &$prefix, &$ret ) {
		$this->displayTitleService->handleLink( $nt->getPrefixedText(), $nt, $html, false );
	}

	/**
	 * Implements BeforePageDisplay hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * Display subtitle if requested
	 *
	 * @since 1.0
	 * @param OutputPage $out the OutputPage object
	 * @param Skin $skin the Skin object
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$this->displayTitleService->setSubtitle( $out );
	}

	/**
	 * Implements OutputPageParserOutput hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/OutputPageParserOutput
	 * Handle talk page title.
	 *
	 * @since 1.0
	 * @param OutputPage $outputPage
	 * @param ParserOutput $parserOutput
	 */
	public function onOutputPageParserOutput( $outputPage, $parserOutput ): void {
		$title = $outputPage->getTitle();
		if ( $title !== null && $title->isTalkPage() ) {
			$subjectPage = Title::castFromLinkTarget( $this->namespaceInfo->getSubjectPage( $title ) );
			if ( $subjectPage->exists() ) {
				$found = $this->displayTitleService->getDisplayTitle( $subjectPage, $displaytitle );
				if ( $found ) {
					$displaytitle = wfMessage( 'displaytitle-talkpagetitle',
						$displaytitle )->plain();
					$parserOutput->setTitleText( $displaytitle );
				}
			}
		}
	}

	/**
	 * Implements ScribuntoExternalLibraries hook.
	 * See https://www.mediawiki.org/wiki/Extension:Scribunto#Other_pages
	 * Handle Scribunto integration.
	 * Registered as a static callback for compatibility with MW 1.39+.
	 *
	 * @since 1.2
	 * @param string $engine engine in use
	 * @param array &$extraLibraries list of registered libraries
	 */
	public static function onScribuntoExternalLibraries( string $engine, array &$extraLibraries ) {
		if ( $engine === 'lua' ) {
			$extraLibraries['mw.ext.displaytitle'] = DisplayTitleLuaLibrary::class;
		}
	}
}
