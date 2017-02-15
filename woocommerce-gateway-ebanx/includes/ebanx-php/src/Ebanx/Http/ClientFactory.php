<?php
/**
 * Copyright (c) 2017, EBANX Tecnologia da Informação Ltda.
 *  All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * Redistributions of source code must retain the above copyright notice, this
 * list of conditions and the following disclaimer.
 *
 * Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * Neither the name of EBANX nor the names of its
 * contributors may be used to endorse or promote products derived from
 * this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

namespace Ebanx\Http;

use Ebanx\Http;

/**
 * HTTP request client wrapper, using curl, with stream fallback
 *
 * @author Guilherme Pressutto guilherme.pressutto@ebanx.com
 */
class ClientFactory
{

    /**
     * Returns CurlClient if curl_* is enabled, else
     * returns StreamClient if php_ini allows url fopen, else
     * throws an exception
     * @return Ebanx\Http\AbstractClient A "Ebanx\Http\AbstractClient" subclass object
     * @throws RuntimeException
     */
    public static function getInstance()
    {
        if(in_array('curl', get_loaded_extensions())) {
            echo "<script>console.log('ClientFactory: Curl');</script>"; //DEBUG: remove before committing
            return new Client();
        } else {
            if(ini_get('allow_url_fopen')) {
                echo "<script>console.log('ClientFactory: Stream');</script>"; //DEBUG: remove before committing
                return new Client();
            } else {
                throw new \RuntimeException('allow_url_fopen must be enabled to use PHP streams.');
            }
        }
        return null;
    }
}
