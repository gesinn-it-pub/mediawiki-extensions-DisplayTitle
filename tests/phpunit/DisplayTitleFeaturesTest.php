<?php

namespace MediaWiki\Extension\DisplayTitle\Tests;

use HtmlArmor;
use JobQueueGroup;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\DisplayTitle\DisplayTitleHooks;
use MediaWiki\Extension\DisplayTitle\DisplayTitlePurgeIncomingLinksJob;
use MediaWiki\Extension\DisplayTitle\DisplayTitleService;
use MediaWiki\Extension\DisplayTitle\ScribuntoHooks;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ParserOptions;
use SkinTemplate;
use WikitextContent;

/**
 * @covers \MediaWiki\Extension\DisplayTitle\DisplayTitleHooks
 * @covers \MediaWiki\Extension\DisplayTitle\DisplayTitleService
 * @covers \MediaWiki\Extension\DisplayTitle\DisplayTitlePurgeIncomingLinksJob
 * @group Database
 */
class DisplayTitleFeaturesTest extends MediaWikiIntegrationTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [
			'AllowDisplayTitle' => true,
			'RestrictDisplayTitle' => false
		] );
		RequestContext::getMain()->setTitle( Title::newFromText( 'Main Page' ) );
	}

	private function createPage( string $titleText, string $wikitext ): void {
		$page = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( $titleText ) );
		$updater = $page->newPageUpdater( $this->getTestSysop()->getUser() );
		$updater->setContent( 'main', new WikitextContent( $wikitext ) );
		$updater->saveRevision(
			CommentStoreComment::newUnsavedComment( 'new test page' ),
			EDIT_AUTOSUMMARY
		);
	}

	private function parse( string $titleText, string $wikitext ): ParserOutput {
		$title = Title::newFromText( $titleText );
		$parserOptions = new ParserOptions( $this->getTestSysop()->getUser() );
		$parserOptions->setRemoveComments( true );
		return $this->getServiceContainer()->getContentRenderer()->getParserOutput(
			new WikitextContent( $wikitext ), $title->toPageIdentity(), null, $parserOptions
		);
	}

	private function newOutputPage( string $titleText ): OutputPage {
		$context = new RequestContext();
		$context->setTitle( Title::newFromText( $titleText ) );
		return new OutputPage( $context );
	}

	private function getHooks(): DisplayTitleHooks {
		return new DisplayTitleHooks(
			$this->getServiceContainer()->getService( 'DisplayTitleService' ),
			$this->getServiceContainer()->getNamespaceInfo()
		);
	}

	private function getService(): DisplayTitleService {
		return $this->getServiceContainer()->getService( 'DisplayTitleService' );
	}

	// #getdisplaytitle parser function

	public function testGetDisplayTitleParserFunction() {
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );

		$output = $this->parse( 'Test Page', '{{#getdisplaytitle:Dingo Page}}' );

		$this->assertStringContainsString( 'Zebra', $output->getRawText() );
	}

	public function testGetDisplayTitleParserFunctionWithoutDisplayTitle() {
		$this->createPage( 'Elephant Page', 'Content' );

		$output = $this->parse( 'Test Page', '{{#getdisplaytitle:Elephant Page}}' );

		$this->assertStringContainsString( 'Elephant Page', $output->getRawText() );
	}

	public function testGetDisplayTitleParserFunctionWithInvalidTitle() {
		$result = $this->getHooks()->getdisplaytitleParserFunction(
			$this->getServiceContainer()->getParser(),
			'<invalid>'
		);

		$this->assertSame( '<invalid>', $result );
	}

	// subtitle

	public function testSubtitleIsAddedForPageWithDisplayTitle() {
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );
		$out = $this->newOutputPage( 'Dingo Page' );

		$this->getHooks()->onBeforePageDisplay( $out, $out->getContext()->getSkin() );

		$this->assertStringContainsString( 'mw-displaytitle-subtitle', $out->getSubtitle() );
		$this->assertStringContainsString( 'Dingo Page', $out->getSubtitle() );
	}

	public function testSubtitleIsNotAddedWithoutDisplayTitle() {
		$this->createPage( 'Elephant Page', 'Content' );
		$out = $this->newOutputPage( 'Elephant Page' );

		$this->getHooks()->onBeforePageDisplay( $out, $out->getContext()->getSkin() );

		$this->assertSame( '', $out->getSubtitle() );
	}

	public function testSubtitleIsAddedForTalkPageOfPageWithDisplayTitle() {
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );
		$out = $this->newOutputPage( 'Talk:Dingo Page' );

		$this->getHooks()->onBeforePageDisplay( $out, $out->getContext()->getSkin() );

		$this->assertStringContainsString( 'mw-displaytitle-subtitle', $out->getSubtitle() );
	}

	public function testSubtitleIsNotAddedForTalkPageOfMissingPage() {
		$out = $this->newOutputPage( 'Talk:Missing Page' );

		$this->getHooks()->onBeforePageDisplay( $out, $out->getContext()->getSkin() );

		$this->assertSame( '', $out->getSubtitle() );
	}

	public function testSubtitleIsNotAddedWhenHidden() {
		$this->overrideConfigValue( 'DisplayTitleHideSubtitle', true );
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );
		$out = $this->newOutputPage( 'Dingo Page' );

		$this->getHooks()->onBeforePageDisplay( $out, $out->getContext()->getSkin() );

		$this->assertSame( '', $out->getSubtitle() );
	}

	// talk page title

	public function testTalkPageTitleUsesDisplayTitleOfSubjectPage() {
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );
		$out = $this->newOutputPage( 'Talk:Dingo Page' );
		$parserOutput = new ParserOutput();

		$this->getHooks()->onOutputPageParserOutput( $out, $parserOutput );

		$this->assertStringContainsString( 'Zebra', $parserOutput->getTitleText() );
	}

	public function testTalkPageTitleIsUntouchedWithoutDisplayTitle() {
		$this->createPage( 'Elephant Page', 'Content' );
		$out = $this->newOutputPage( 'Talk:Elephant Page' );
		$parserOutput = new ParserOutput();

		$this->getHooks()->onOutputPageParserOutput( $out, $parserOutput );

		$this->assertSame( '', $parserOutput->getTitleText() );
	}

	public function testPageTitleIsUntouchedOnNonTalkPage() {
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );
		$out = $this->newOutputPage( 'Dingo Page' );
		$parserOutput = new ParserOutput();

		$this->getHooks()->onOutputPageParserOutput( $out, $parserOutput );

		$this->assertSame( '', $parserOutput->getTitleText() );
	}

	// DisplayTitleService

	public function testGetDisplayTitleReturnsFalseForSpecialPage() {
		$displayTitle = null;

		$this->assertFalse(
			$this->getService()->getDisplayTitle( Title::newFromText( 'Special:UserLogin' ), $displayTitle )
		);
	}

	public function testGetDisplayTitleWrapsResultInHtmlArmor() {
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );
		$displayTitle = null;

		$found = $this->getService()->getDisplayTitle( Title::newFromText( 'Dingo Page' ), $displayTitle, true );

		$this->assertTrue( $found );
		$this->assertInstanceOf( HtmlArmor::class, $displayTitle );
		$this->assertSame( 'Zebra', HtmlArmor::getHtml( $displayTitle ) );
	}

	public function testGetDisplayTitleOfRedirectWithoutDisplayTitleUsesTargetName() {
		$this->createPage( 'Elephant Page', 'Content' );
		$this->createPage( 'Redirect To Elephant Page', '#REDIRECT [[Elephant Page]]' );
		$displayTitle = null;

		$found = $this->getService()->getDisplayTitle(
			Title::newFromText( 'Redirect To Elephant Page' ), $displayTitle, true
		);

		$this->assertTrue( $found );
		$this->assertSame( 'Elephant Page', HtmlArmor::getHtml( $displayTitle ) );
	}

	public function testHandleLinkIgnoresExcludedPages() {
		$this->overrideConfigValue( 'DisplayTitleExcludes', [ 'Excluded Page' ] );
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );
		$html = 'Dingo Page';

		$this->getService()->handleLink( 'Excluded Page', Title::newFromText( 'Dingo Page' ), $html, false );

		$this->assertSame( 'Dingo Page', $html );
	}

	public function testHandleLinkIgnoresRedirectLinkingToItsTarget() {
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );
		$this->createPage( 'Redirect To Dingo Page', '#REDIRECT [[Dingo Page]]' );
		$html = 'Dingo Page';

		$this->getService()->handleLink(
			'Redirect To Dingo Page', Title::newFromText( 'Redirect To Dingo Page' ), $html, false
		);

		$this->assertSame( 'Dingo Page', $html );
	}

	public function testHandleLinkReplacesPlainLinkText() {
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );
		$html = 'Dingo Page';

		$this->getService()->handleLink( 'Test Page', Title::newFromText( 'Dingo Page' ), $html, false );

		$this->assertSame( 'Zebra', $html );
	}

	public function testHandleLinkKeepsCustomizedIntegerLinkText() {
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );
		$html = 5;

		$this->getService()->handleLink( 'Test Page', Title::newFromText( 'Dingo Page' ), $html, false );

		$this->assertSame( 5, $html );
	}

	public function testHandleLinkStripsHighlightingFromHtmlArmor() {
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );
		$html = new HtmlArmor( '<span class="searchmatch">Dingo</span> Page' );

		$this->getService()->handleLink( 'Test Page', Title::newFromText( 'Dingo Page' ), $html, true );

		$this->assertInstanceOf( HtmlArmor::class, $html );
		$this->assertSame( 'Zebra', HtmlArmor::getHtml( $html ) );
	}

	/**
	 * @dataProvider provideCustomizedFragmentLinkTexts
	 */
	public function testHandleLinkKeepsCustomizedFragmentLinkText( string $linkText ) {
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );
		$html = $linkText;

		$this->getService()->handleLink( 'Test Page', Title::newFromText( 'Dingo Page#Section' ), $html, false );

		$this->assertSame( $linkText, $html );
	}

	public static function provideCustomizedFragmentLinkTexts() {
		yield 'fragment only' => [ '#Section' ];
		yield 'title without fragment' => [ 'Dingo Page' ];
	}

	public function testHandleLinkReplacesTitleOfLinkWithFragment() {
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );
		$html = 'Dingo Page#Section';

		$this->getService()->handleLink( 'Test Page', Title::newFromText( 'Dingo Page#Section' ), $html, false );

		$this->assertSame( 'Zebra', $html );
	}

	public function testHandleLinkWrapsTitleOfLinkWithFragmentInHtmlArmor() {
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );
		$html = 'Dingo Page#Section';

		$this->getService()->handleLink( 'Test Page', Title::newFromText( 'Dingo Page#Section' ), $html, true );

		$this->assertInstanceOf( HtmlArmor::class, $html );
		$this->assertSame( 'Zebra', HtmlArmor::getHtml( $html ) );
	}

	// personal urls

	private function newSkinTemplate( $user ): SkinTemplate {
		$skinTemplate = $this->createMock( SkinTemplate::class );
		$skinTemplate->method( 'getUser' )->willReturn( $user );
		return $skinTemplate;
	}

	public function testUserPageLinksUseDisplayTitleOfUserPage() {
		$user = $this->getTestSysop()->getUser();
		$this->createPage( $user->getUserPage()->getPrefixedText(), '{{DISPLAYTITLE:Zebra}}' );
		$links = [
			'user-menu' => [ 'userpage' => [ 'text' => $user->getName() ] ],
			'user-page' => [ 'userpage' => [ 'text' => $user->getName() ] ],
		];

		$this->getHooks()->onSkinTemplateNavigation__Universal( $this->newSkinTemplate( $user ), $links );

		$this->assertSame( 'Zebra', $links['user-menu']['userpage']['text'] );
		$this->assertSame( 'Zebra', $links['user-page']['userpage']['text'] );
	}

	public function testUserPageLinkInPageMenuUsesDisplayTitleOfUserPage() {
		$user = $this->getTestSysop()->getUser();
		$this->createPage( $user->getUserPage()->getPrefixedText(), '{{DISPLAYTITLE:Zebra}}' );
		$links = [ 'user-page' => [ 'userpage' => [ 'text' => $user->getName() ] ] ];

		$this->getHooks()->onSkinTemplateNavigation__Universal( $this->newSkinTemplate( $user ), $links );

		$this->assertSame( 'Zebra', $links['user-page']['userpage']['text'] );
	}

	public function testUserPageLinksAreUntouchedForAnonymousUsers() {
		$links = [ 'user-menu' => [ 'userpage' => [ 'text' => '127.0.0.1' ] ] ];

		$this->getHooks()->onSkinTemplateNavigation__Universal(
			$this->newSkinTemplate( $this->getServiceContainer()->getUserFactory()->newAnonymous( '127.0.0.1' ) ),
			$links
		);

		$this->assertSame( '127.0.0.1', $links['user-menu']['userpage']['text'] );
	}

	// purge of incoming links

	public function testParserAfterParseFlagsChangedDisplayTitle() {
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );

		$output = $this->parse( 'Dingo Page', '{{DISPLAYTITLE:Gnu}}' );

		$this->assertSame( [ 'displayTitleChanged' => true ], $output->getExtensionData( 'displayTitle' ) );
	}

	public function testParserAfterParseDoesNotFlagUnchangedDisplayTitle() {
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );

		$output = $this->parse( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );

		$this->assertNull( $output->getExtensionData( 'displayTitle' ) );
	}

	public function testParserAfterParseDoesNotFlagPageWithoutDisplayTitle() {
		$this->createPage( 'Elephant Page', 'Content' );

		$output = $this->parse( 'Elephant Page', 'Other content' );

		$this->assertNull( $output->getExtensionData( 'displayTitle' ) );
	}

	/**
	 * @dataProvider provideRevisionDataUpdates
	 */
	public function testRevisionDataUpdatesPushesPurgeJobOnlyOnChange( ?array $extensionData, bool $expectJob ) {
		$parserOutput = new ParserOutput();
		if ( $extensionData !== null ) {
			$parserOutput->setExtensionData( 'displayTitle', $extensionData );
		}
		$renderedRevision = $this->createMock( RenderedRevision::class );
		$renderedRevision->method( 'getRevisionParserOutput' )->willReturn( $parserOutput );

		$jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$jobQueueGroup->expects( $expectJob ? $this->once() : $this->never() )
			->method( 'lazyPush' )
			->with( $this->isInstanceOf( DisplayTitlePurgeIncomingLinksJob::class ) );
		$this->setService( 'JobQueueGroup', $jobQueueGroup );

		$updates = [];
		$this->getHooks()->onRevisionDataUpdates( Title::newFromText( 'Dingo Page' ), $renderedRevision, $updates );
	}

	public static function provideRevisionDataUpdates() {
		yield 'display title changed' => [ [ 'displayTitleChanged' => true ], true ];
		yield 'display title not changed' => [ [ 'displayTitleChanged' => false ], false ];
		yield 'no display title data' => [ null, false ];
	}

	public function testPurgeJobRunsForPageWithIncomingLinks() {
		$this->createPage( 'Dingo Page', '{{DISPLAYTITLE:Zebra}}' );
		$this->createPage( 'Linking Page', '[[Dingo Page]]' );
		$linkingPage = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( 'Linking Page' ) );
		$this->db->newUpdateQueryBuilder()
			->update( 'page' )
			->set( [ 'page_touched' => $this->db->timestamp( '20200101000000' ) ] )
			->where( [ 'page_id' => $linkingPage->getId() ] )
			->caller( __METHOD__ )->execute();
		$linkingPage->getTitle()->resetArticleID( $linkingPage->getId() );

		$job = new DisplayTitlePurgeIncomingLinksJob(
			[ 'pageid' => Title::newFromText( 'Dingo Page' )->getArticleID() ]
		);

		$this->assertTrue( $job->run() );
		$touched = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( 'Linking Page' ) )->getTouched();
		$this->assertGreaterThan( '20200101000000', $touched );
	}

	public function testPurgeJobSucceedsForMissingPage() {
		$job = new DisplayTitlePurgeIncomingLinksJob( [ 'pageid' => 999999999 ] );

		$this->assertTrue( $job->run() );
	}

	// Scribunto

	/**
	 * @covers \MediaWiki\Extension\DisplayTitle\ScribuntoHooks
	 */
	public function testScribuntoHooksRegisterLuaLibrary() {
		if ( !interface_exists( 'MediaWiki\\Extension\\Scribunto\\Hooks\\ScribuntoExternalLibrariesHook' ) ) {
			$this->markTestSkipped( 'Scribunto is not installed' );
		}
		$libraries = [];

		( new ScribuntoHooks() )->onScribuntoExternalLibraries( 'lua', $libraries );

		$this->assertArrayHasKey( 'mw.ext.displaytitle', $libraries );
	}

	/**
	 * @covers \MediaWiki\Extension\DisplayTitle\ScribuntoHooks
	 */
	public function testScribuntoHooksIgnoreOtherEngines() {
		if ( !interface_exists( 'MediaWiki\\Extension\\Scribunto\\Hooks\\ScribuntoExternalLibrariesHook' ) ) {
			$this->markTestSkipped( 'Scribunto is not installed' );
		}
		$libraries = [];

		( new ScribuntoHooks() )->onScribuntoExternalLibraries( 'other', $libraries );

		$this->assertSame( [], $libraries );
	}
}
