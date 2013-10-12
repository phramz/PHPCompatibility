<?php
/**
 * PHPCompatibility_Sniffs_PHP_ForbiddenCallTimePassByReference.
 *
 * PHP version 5.4
 *
 * @category  PHP
 * @package   PHPCompatibility
 * @author    Gary Rogers <gmrwebde@gmail.com>
 * @author    Florian Grandel <jerico.dev@gmail.com>
 * @copyright 2009 Florian Grandel
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */

/**
 * PHPCompatibility_Sniffs_PHP_ForbiddenCallTimePassByReference.
 *
 * Discourages the use of call time pass by references
 *
 * PHP version 5.4
 *
 * @category  PHP
 * @package   PHPCompatibility
 * @author    Gary Rogers <gmrwebde@gmail.com>
 * @author    Florian Grandel <jerico.dev@gmail.com>
 * @copyright 2009 Florian Grandel
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
class PHPCompatibility_Sniffs_PHP_ForbiddenCallTimePassByReferenceSniff implements PHP_CodeSniffer_Sniff
{

    /**
     * If true, an error will be thrown; otherwise a warning.
     *
     * @var bool
     */
    protected $error = true;

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return array(T_STRING);

    }//end register()

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token
     *                                        in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        if (
            !isset($phpcsFile->phpcs->cli->settingsStandard['testVersion'])
            ||
            (
                isset($phpcsFile->phpcs->cli->settingsStandard['testVersion'])
                &&
                version_compare($phpcsFile->phpcs->cli->settingsStandard['testVersion'], '5.4') >= 0
            )
        ) {
            $tokens = $phpcsFile->getTokens();

            // Skip tokens that are the names of functions or classes
            // within their definitions. For example: function myFunction...
            // "myFunction" is T_STRING but we should skip because it is not a
            // function or method *call*.
            $functionName = $stackPtr;
            $findTokens   = array_merge(
                PHP_CodeSniffer_Tokens::$emptyTokens,
                array(T_BITWISE_AND)
            );

            $functionKeyword = $phpcsFile->findPrevious(
                $findTokens,
                ($stackPtr - 1),
                null,
                true
            );

            if ($tokens[$functionKeyword]['code'] === T_FUNCTION
                || $tokens[$functionKeyword]['code'] === T_CLASS
            ) {
                return;
            }

            // If the next non-whitespace token after the function or method call
            // is not an opening parenthesis then it cant really be a *call*.
            $openBracket = $phpcsFile->findNext(
                PHP_CodeSniffer_Tokens::$emptyTokens,
                ($functionName + 1),
                null,
                true
            );

            if ($tokens[$openBracket]['code'] !== T_OPEN_PARENTHESIS) {
                return;
            }

            $closeBracket = $tokens[$openBracket]['parenthesis_closer'];

            $nextSeparator = $openBracket;
            while (($nextSeparator = $phpcsFile->findNext(T_VARIABLE, ($nextSeparator + 1), $closeBracket)) !== false) {
                // Make sure the variable belongs directly to this function call
                // and is not inside a nested function call or array.
                $brackets    = $tokens[$nextSeparator]['nested_parenthesis'];
                $lastBracket = array_pop($brackets);
                if ($lastBracket !== $closeBracket) {
                    continue;
                }

                // Checking this: $value = my_function(...[*]$arg...).
                $tokenBefore = $phpcsFile->findPrevious(
                    PHP_CodeSniffer_Tokens::$emptyTokens,
                    ($nextSeparator - 1),
                    null,
                    true
                );

                if ($tokens[$tokenBefore]['code'] === T_BITWISE_AND) {
                    // Checking this: $value = my_function(...[*]&$arg...).
                    $tokenBefore = $phpcsFile->findPrevious(
                        PHP_CodeSniffer_Tokens::$emptyTokens,
                        ($tokenBefore - 1),
                        null,
                        true
                    );

                    // We have to exclude all uses of T_BITWISE_AND that are not
                    // references. We use a blacklist approach as we prefer false
                    // positives to not identifying a pass-by-reference call at all.
                    // The blacklist may not yet be complete.
                    switch ($tokens[$tokenBefore]['code']) {
                    case T_LNUMBER:
                    case T_VARIABLE:
                    case T_CLOSE_SQUARE_BRACKET:
                    case T_CLOSE_PARENTHESIS:
                        // In these cases T_BITWISE_AND represents
                        // the bitwise and operator.
                        continue;

                    default:
                        // T_BITWISE_AND represents a pass-by-reference.
                        $error = 'Using a call-time pass-by-reference is prohibited since php 5.4';
                        $phpcsFile->addError($error, $tokenBefore, 'NotAllowed');
                        break;
                    }
                }//end if
            }//end while
        }
    }//end process()

}//end class
