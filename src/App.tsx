11:02:35.045 Running build in Washington, D.C., USA (East) – iad1
11:02:35.046 Build machine configuration: 2 cores, 8 GB
11:02:35.057 Cloning github.com/HEMONY/project-completion-hub-ec01f07e (Branch: main, Commit: 73d2598)
11:02:35.058 Skipping build cache, deployment was triggered without cache.
11:02:35.968 Cloning completed: 911.000ms
11:02:36.351 Running "vercel build"
11:02:37.106 Vercel CLI 51.6.1
11:02:37.675 Installing dependencies...
11:02:50.522 npm warn deprecated whatwg-encoding@2.0.0: Use @exodus/bytes instead for a more spec-conformant and faster implementation
11:02:50.700 npm warn deprecated abab@2.0.6: Use your platform's native atob() and btoa() methods instead
11:02:50.985 npm warn deprecated domexception@4.0.0: Use your platform's native DOMException instead
11:02:57.835 
11:02:57.836 added 516 packages in 20s
11:02:57.836 
11:02:57.837 98 packages are looking for funding
11:02:57.837   run npm fund for details
11:02:57.900 Running "npm run build"
11:02:58.014 
11:02:58.014 > vite_react_shadcn_ts@0.0.0 build
11:02:58.015 > vite build
11:02:58.015 
11:02:58.251  [36mvite v5.4.19  [32mbuilding for production... [36m [39m
11:02:58.304 transforming...
11:02:58.560 Browserslist: browsers data (caniuse-lite) is 10 months old. Please run:
11:02:58.560   npx update-browserslist-db@latest
11:02:58.561   Why you should do it regularly: https://github.com/browserslist/update-db#readme
11:02:58.769  [32m✓ [39m 3 modules transformed.
11:02:58.771  [31mx [39m Build failed in 497ms
11:02:58.772  [31merror during build:
11:02:58.772  [31m[vite:esbuild] Transform failed with 1 error:
11:02:58.772 /vercel/path0/src/App.tsx:41:57: ERROR: The character ">" is not valid inside a JSX element [31m
11:02:58.772 file:  [36m/vercel/path0/src/App.tsx:41:57 [31m
11:02:58.772  [33m
11:02:58.773  [33mThe character ">" is not valid inside a JSX element [33m
11:02:58.773 39 |                <Route path="/cdd/:entityId" element={<CddVerification />} />
11:02:58.773 40 |                <Route path="/admin" element={<Admin />} />
11:02:58.773 41 |                <Route path="*" element={<NotFound />} /> />
11:02:58.773    |                                                           ^
11:02:58.773 42 |              </Routes>
11:02:58.773 43 |            </BrowserRouter>
11:02:58.773  [31m
11:02:58.773     at failureErrorWithLog (/vercel/path0/node_modules/esbuild/lib/main.js:1472:15)
11:02:58.774     at /vercel/path0/node_modules/esbuild/lib/main.js:755:50
11:02:58.774     at responseCallbacks.<computed> (/vercel/path0/node_modules/esbuild/lib/main.js:622:9)
11:02:58.774     at handleIncomingPacket (/vercel/path0/node_modules/esbuild/lib/main.js:677:12)
11:02:58.774     at Socket.readFromStdout (/vercel/path0/node_modules/esbuild/lib/main.js:600:7)
11:02:58.774     at Socket.emit (node:events:508:28)
11:02:58.775     at addChunk (node:internal/streams/readable:563:12)
11:02:58.776     at readableAddChunkPushByteMode (node:internal/streams/readable:514:3)
11:02:58.776     at Readable.push (node:internal/streams/readable:394:5)
11:02:58.776     at Pipe.onStreamRead (node:internal/stream_base_commons:189:23) [39m
11:02:58.793 Error: Command "npm run build" exited with 1
