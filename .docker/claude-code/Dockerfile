FROM sail-8.5/app:latest

USER sail

# Install Claude Code
RUN curl -fsSL https://claude.ai/install.sh | bash

# add ~/.local/bin to PATH
RUN echo 'export PATH="$HOME/.local/bin:$PATH"' >> ~/.bashrc

USER root

# Command to keep the container alive (Interactive Shell)
CMD ["/bin/bash"]
